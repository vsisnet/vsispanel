<?php

declare(strict_types=1);

namespace App\Modules\DNS\Services;

use App\Modules\DNS\Models\DnsRecord;
use App\Modules\DNS\Models\DnsZone;
use App\Modules\Domain\Models\Domain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PowerDnsService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $serverId;

    public function __construct()
    {
        $this->apiUrl = config('vsispanel.dns.powerdns_api_url', 'http://127.0.0.1:8081');
        $this->apiKey = config('vsispanel.dns.powerdns_api_key', '');
        $this->serverId = config('vsispanel.dns.powerdns_server_id', 'localhost');
    }

    /**
     * Create a DNS zone for a domain.
     */
    public function createZone(Domain $domain, string $serverIp, array $options = []): DnsZone
    {
        $zoneName = $domain->name . '.';
        $primaryNs = $options['primary_ns'] ?? config('vsispanel.dns.primary_ns', 'ns1.' . $domain->name);
        $adminEmail = $options['admin_email'] ?? config('vsispanel.dns.admin_email', 'admin.' . $domain->name);

        return DB::transaction(function () use ($domain, $zoneName, $primaryNs, $adminEmail, $serverIp) {
            // Create zone in database
            $zone = DnsZone::create([
                'domain_id' => $domain->id,
                'zone_name' => rtrim($zoneName, '.'),
                'serial' => (int) now()->format('Ymd') . '01',
                'primary_ns' => $primaryNs,
                'admin_email' => str_replace('@', '.', $adminEmail),
                'refresh' => config('vsispanel.dns.soa_refresh', 10800),
                'retry' => config('vsispanel.dns.soa_retry', 3600),
                'expire' => config('vsispanel.dns.soa_expire', 604800),
                'minimum_ttl' => config('vsispanel.dns.soa_minimum', 3600),
                'status' => 'active',
            ]);

            // Create default records
            $this->createDefaultRecords($zone, $serverIp);

            // Sync with PowerDNS API
            $this->syncZoneToPowerDns($zone);

            return $zone;
        });
    }

    /**
     * Create default DNS records for a zone.
     */
    protected function createDefaultRecords(DnsZone $zone, string $serverIp): void
    {
        $records = [
            // SOA record
            [
                'name' => '@',
                'type' => 'SOA',
                'content' => $zone->getSoaContent(),
                'ttl' => 86400,
            ],
            // NS records
            [
                'name' => '@',
                'type' => 'NS',
                'content' => rtrim($zone->primary_ns, '.') . '.',
                'ttl' => 86400,
            ],
            // A record for root domain
            [
                'name' => '@',
                'type' => 'A',
                'content' => $serverIp,
                'ttl' => 3600,
            ],
            // www CNAME
            [
                'name' => 'www',
                'type' => 'CNAME',
                'content' => $zone->zone_name . '.',
                'ttl' => 3600,
            ],
        ];

        foreach ($records as $recordData) {
            $zone->records()->create($recordData);
        }
    }

    /**
     * Delete a DNS zone.
     */
    public function deleteZone(DnsZone $zone): void
    {
        // Delete from PowerDNS
        $this->deleteZoneFromPowerDns($zone);

        // Delete from database (soft delete)
        $zone->delete();
    }

    /**
     * Add a DNS record to a zone.
     */
    public function addRecord(DnsZone $zone, array $data): DnsRecord
    {
        // Normalize the record name - strip zone suffix and trailing dots
        $data['name'] = $this->normalizeRecordName($data['name'], $zone->zone_name);

        // Validate the record
        $this->validateRecord($data['type'], $data['name'], $data['content']);

        $record = $zone->records()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'content' => $data['content'],
            'ttl' => $data['ttl'] ?? 3600,
            'priority' => $data['priority'] ?? null,
            'weight' => $data['weight'] ?? null,
            'port' => $data['port'] ?? null,
            'disabled' => $data['disabled'] ?? false,
        ]);

        // Update zone serial
        $zone->incrementSerial();

        // Sync with PowerDNS
        $this->syncZoneToPowerDns($zone);

        return $record;
    }

    /**
     * Update a DNS record.
     */
    public function updateRecord(DnsRecord $record, array $data): DnsRecord
    {
        // Normalize name if provided
        if (isset($data['name'])) {
            $data['name'] = $this->normalizeRecordName($data['name'], $record->zone->zone_name);
        }

        if (isset($data['content'])) {
            $this->validateRecord(
                $data['type'] ?? $record->type,
                $data['name'] ?? $record->name,
                $data['content']
            );
        }

        $record->update($data);

        // Update zone serial
        $record->zone->incrementSerial();

        // Sync with PowerDNS
        $this->syncZoneToPowerDns($record->zone);

        return $record->fresh();
    }

    /**
     * Delete a DNS record.
     */
    public function deleteRecord(DnsRecord $record): void
    {
        $zone = $record->zone;

        // Delete from database
        $record->delete();

        // Update zone serial
        $zone->incrementSerial();

        // Sync with PowerDNS
        $this->syncZoneToPowerDns($zone);
    }

    /**
     * Toggle record enabled/disabled status.
     */
    public function toggleRecord(DnsRecord $record): DnsRecord
    {
        $record->update(['disabled' => !$record->disabled]);

        // Update zone serial
        $record->zone->incrementSerial();

        // Sync with PowerDNS
        $this->syncZoneToPowerDns($record->zone);

        return $record->fresh();
    }

    /**
     * Validate a DNS record.
     */
    public function validateRecord(string $type, string $name, string $content): bool
    {
        switch ($type) {
            case 'A':
                if (!filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    throw new RuntimeException('Invalid IPv4 address for A record.');
                }
                break;

            case 'AAAA':
                if (!filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    throw new RuntimeException('Invalid IPv6 address for AAAA record.');
                }
                break;

            case 'CNAME':
                if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*[a-zA-Z0-9]\.?$/', $content)) {
                    throw new RuntimeException('Invalid hostname for CNAME record.');
                }
                break;

            case 'MX':
                if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*[a-zA-Z0-9]\.?$/', $content)) {
                    throw new RuntimeException('Invalid mail server hostname for MX record.');
                }
                break;

            case 'TXT':
                if (strlen($content) > 4096) {
                    throw new RuntimeException('TXT record content exceeds maximum length.');
                }
                break;

            case 'NS':
                if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*[a-zA-Z0-9]\.?$/', $content)) {
                    throw new RuntimeException('Invalid nameserver hostname for NS record.');
                }
                break;

            case 'CAA':
                // CAA format: flag tag value (e.g., "0 issue letsencrypt.org")
                if (!preg_match('/^\d+\s+\w+\s+.+$/', $content)) {
                    throw new RuntimeException('Invalid CAA record format. Expected: flag tag value');
                }
                break;

            case 'SRV':
                // Content should be the target hostname; priority, weight, port are separate fields
                if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*[a-zA-Z0-9]\.?$/', $content)) {
                    throw new RuntimeException('Invalid target hostname for SRV record.');
                }
                break;
        }

        return true;
    }

    /**
     * Apply a DNS template to a zone.
     */
    public function applyTemplate(DnsZone $zone, string $templateName, array $variables = []): void
    {
        $templatePath = resource_path("views/templates/dns/{$templateName}.json");

        if (!File::exists($templatePath)) {
            throw new RuntimeException("DNS template '{$templateName}' not found.");
        }

        $template = json_decode(File::get($templatePath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid DNS template format.");
        }

        foreach ($template['records'] ?? [] as $recordData) {
            // Replace variables in content
            $content = $recordData['content'];
            foreach ($variables as $key => $value) {
                $content = str_replace('{{' . $key . '}}', $value, $content);
            }
            $content = str_replace('{{domain}}', $zone->zone_name, $content);

            // Check if record already exists
            $existing = $zone->records()
                ->where('name', $recordData['name'])
                ->where('type', $recordData['type'])
                ->first();

            if ($existing) {
                $existing->update(['content' => $content]);
            } else {
                $zone->records()->create([
                    'name' => $recordData['name'],
                    'type' => $recordData['type'],
                    'content' => $content,
                    'ttl' => $recordData['ttl'] ?? 3600,
                    'priority' => $recordData['priority'] ?? null,
                ]);
            }
        }

        // Update zone serial
        $zone->incrementSerial();

        // Sync with PowerDNS
        $this->syncZoneToPowerDns($zone);
    }

    /**
     * Export zone in BIND format.
     */
    public function exportZone(DnsZone $zone): string
    {
        $output = "; Zone file for {$zone->zone_name}\n";
        $output .= "; Generated by VSISPanel on " . now()->toDateTimeString() . "\n\n";

        $output .= "\$ORIGIN {$zone->zone_name}.\n";
        $output .= "\$TTL {$zone->minimum_ttl}\n\n";

        // SOA record
        $output .= "@\tIN\tSOA\t{$zone->primary_ns}. {$zone->admin_email}. (\n";
        $output .= "\t\t\t{$zone->serial}\t; Serial\n";
        $output .= "\t\t\t{$zone->refresh}\t\t; Refresh\n";
        $output .= "\t\t\t{$zone->retry}\t\t; Retry\n";
        $output .= "\t\t\t{$zone->expire}\t\t; Expire\n";
        $output .= "\t\t\t{$zone->minimum_ttl} )\t\t; Minimum TTL\n\n";

        // Other records (skip SOA as we already added it)
        foreach ($zone->records()->where('type', '!=', 'SOA')->get() as $record) {
            $name = $record->name === '@' ? '@' : $record->name;
            $content = $record->formatContent();

            // Add disabled comment
            if ($record->disabled) {
                $output .= "; DISABLED: ";
            }

            $output .= "{$name}\t{$record->ttl}\tIN\t{$record->type}\t{$content}\n";
        }

        return $output;
    }

    /**
     * Get zone from PowerDNS API.
     */
    public function getZoneFromPowerDns(string $zoneName): ?array
    {
        try {
            $response = $this->apiRequest('GET', "/api/v1/servers/{$this->serverId}/zones/{$zoneName}.");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Sync zone to PowerDNS API.
     */
    public function syncZoneToPowerDns(DnsZone $zone): void
    {
        $zoneName = $zone->zone_name . '.';

        // Check if zone exists in PowerDNS
        $existingZone = $this->getZoneFromPowerDns($zone->zone_name);

        if ($existingZone) {
            // Update existing zone
            $this->updateZoneInPowerDns($zone);
        } else {
            // Create new zone
            $this->createZoneInPowerDns($zone);
        }
    }

    /**
     * Create zone in PowerDNS.
     */
    protected function createZoneInPowerDns(DnsZone $zone): void
    {
        $zoneName = $zone->zone_name . '.';

        $rrsets = $this->buildRrsets($zone);

        $payload = [
            'name' => $zoneName,
            'kind' => 'Native',
            'nameservers' => [],
            'rrsets' => $rrsets,
        ];

        $response = $this->apiRequest('POST', "/api/v1/servers/{$this->serverId}/zones", $payload);

        if (!$response->successful() && $response->status() !== 201) {
            throw new RuntimeException('Failed to create zone in PowerDNS: ' . $response->body());
        }
    }

    /**
     * Update zone in PowerDNS.
     */
    protected function updateZoneInPowerDns(DnsZone $zone): void
    {
        $zoneName = $zone->zone_name . '.';

        $rrsets = $this->buildRrsets($zone);

        // Set changetype to REPLACE for all rrsets
        foreach ($rrsets as &$rrset) {
            $rrset['changetype'] = 'REPLACE';
        }

        $payload = ['rrsets' => $rrsets];

        $response = $this->apiRequest('PATCH', "/api/v1/servers/{$this->serverId}/zones/{$zoneName}", $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to update zone in PowerDNS: ' . $response->body());
        }
    }

    /**
     * Delete zone from PowerDNS.
     */
    protected function deleteZoneFromPowerDns(DnsZone $zone): void
    {
        $zoneName = $zone->zone_name . '.';

        $response = $this->apiRequest('DELETE', "/api/v1/servers/{$this->serverId}/zones/{$zoneName}");

        // 204 No Content is success, 404 Not Found is also acceptable
        if (!$response->successful() && $response->status() !== 404) {
            throw new RuntimeException('Failed to delete zone from PowerDNS: ' . $response->body());
        }
    }

    /**
     * Build rrsets array for PowerDNS API.
     */

    /**
     * Normalize a record name to relative form (e.g., '@' for zone apex, 'www' for subdomain).
     */
    protected function normalizeRecordName(string $name, string $zoneName): string
    {
        // Remove trailing dot
        $name = rtrim($name, '.');
        $zoneName = rtrim($zoneName, '.');

        // If name equals zone name, it's the apex
        if ($name === $zoneName) {
            return '@';
        }

        // If name ends with .zoneName, strip it
        $suffix = '.' . $zoneName;
        if (str_ends_with($name, $suffix)) {
            return substr($name, 0, -strlen($suffix));
        }

        // Already relative or '@'
        return $name;
    }

    protected function buildRrsets(DnsZone $zone): array
    {
        $zoneName = $zone->zone_name . '.';
        $rrsets = [];

        // Group records by name and type
        $grouped = $zone->records()
            ->enabled()
            ->get()
            ->groupBy(function ($record) {
                return $record->name . '|' . $record->type;
            });

        foreach ($grouped as $key => $records) {
            [$name, $type] = explode('|', $key);

            $fqdn = $name === '@' ? $zoneName : $name . '.' . $zoneName;

            $recordsArray = [];
            foreach ($records as $record) {
                $recordsArray[] = [
                    'content' => $record->formatContent(),
                    'disabled' => $record->disabled,
                ];
            }

            $rrsets[] = [
                'name' => $fqdn,
                'type' => $type,
                'ttl' => $records->first()->ttl,
                'records' => $recordsArray,
            ];
        }

        return $rrsets;
    }

    /**
     * Make API request to PowerDNS.
     */
    protected function apiRequest(string $method, string $endpoint, array $data = null)
    {
        $url = rtrim($this->apiUrl, '/') . $endpoint;

        $request = Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30);

        switch (strtoupper($method)) {
            case 'GET':
                return $request->get($url);
            case 'POST':
                return $request->post($url, $data ?? []);
            case 'PUT':
                return $request->put($url, $data ?? []);
            case 'PATCH':
                return $request->patch($url, $data ?? []);
            case 'DELETE':
                return $request->delete($url);
            default:
                throw new RuntimeException("Unsupported HTTP method: {$method}");
        }
    }

    /**
     * Add mail-related DNS records (MX, SPF, DKIM, DMARC).
     */
    public function addMailRecords(DnsZone $zone, string $serverIp, array $spfDkimDmarc): void
    {
        // MX record
        $this->addRecord($zone, [
            'name' => '@',
            'type' => 'MX',
            'content' => 'mail.' . $zone->zone_name . '.',
            'priority' => 10,
            'ttl' => 3600,
        ]);

        // mail A record
        $this->addRecord($zone, [
            'name' => 'mail',
            'type' => 'A',
            'content' => $serverIp,
            'ttl' => 3600,
        ]);

        // SPF record
        if (isset($spfDkimDmarc['spf'])) {
            $this->addRecord($zone, [
                'name' => '@',
                'type' => 'TXT',
                'content' => $spfDkimDmarc['spf']['content'],
                'ttl' => 3600,
            ]);
        }

        // DKIM record
        if (isset($spfDkimDmarc['dkim'])) {
            $dkimName = str_replace('.' . $zone->zone_name, '', $spfDkimDmarc['dkim']['name']);
            $this->addRecord($zone, [
                'name' => $dkimName,
                'type' => 'TXT',
                'content' => $spfDkimDmarc['dkim']['content'],
                'ttl' => 3600,
            ]);
        }

        // DMARC record
        if (isset($spfDkimDmarc['dmarc'])) {
            $this->addRecord($zone, [
                'name' => '_dmarc',
                'type' => 'TXT',
                'content' => $spfDkimDmarc['dmarc']['content'],
                'ttl' => 3600,
            ]);
        }
    }

    /**
     * Get available DNS templates.
     */
    public function getAvailableTemplates(): array
    {
        $templatesPath = resource_path('views/templates/dns');

        if (!File::isDirectory($templatesPath)) {
            return [];
        }

        $files = File::files($templatesPath);
        $templates = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'json') {
                $name = $file->getFilenameWithoutExtension();
                $content = json_decode(File::get($file->getPathname()), true);

                $templates[] = [
                    'name' => $name,
                    'label' => $content['label'] ?? $name,
                    'description' => $content['description'] ?? '',
                    'records_count' => count($content['records'] ?? []),
                ];
            }
        }

        return $templates;
    }

    /**
     * Get template preview with variable substitution.
     */
    public function getTemplatePreview(string $templateName, string $domain, array $variables = []): array
    {
        $templatePath = resource_path("views/templates/dns/{$templateName}.json");

        if (!File::exists($templatePath)) {
            throw new RuntimeException("DNS template '{$templateName}' not found.");
        }

        $template = json_decode(File::get($templatePath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid DNS template format.");
        }

        $previewRecords = [];
        foreach ($template['records'] ?? [] as $recordData) {
            $content = $recordData['content'];
            foreach ($variables as $key => $value) {
                $content = str_replace('{{' . $key . '}}', $value, $content);
            }
            $content = str_replace('{{domain}}', $domain, $content);

            $previewRecords[] = [
                'name' => $recordData['name'],
                'type' => $recordData['type'],
                'content' => $content,
                'ttl' => $recordData['ttl'] ?? 3600,
                'priority' => $recordData['priority'] ?? null,
            ];
        }

        return [
            'label' => $template['label'] ?? $templateName,
            'description' => $template['description'] ?? '',
            'records' => $previewRecords,
        ];
    }

    /**
     * Bulk add DNS records to a zone.
     */
    public function bulkAddRecords(DnsZone $zone, array $records): array
    {
        $addedRecords = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($records as $index => $recordData) {
                try {
                    $this->validateRecord($recordData['type'], $recordData['name'], $recordData['content']);

                    $record = $zone->records()->create([
                        'name' => $recordData['name'],
                        'type' => $recordData['type'],
                        'content' => $recordData['content'],
                        'ttl' => $recordData['ttl'] ?? 3600,
                        'priority' => $recordData['priority'] ?? null,
                        'weight' => $recordData['weight'] ?? null,
                        'port' => $recordData['port'] ?? null,
                        'disabled' => $recordData['disabled'] ?? false,
                    ]);

                    $addedRecords[] = $record;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'record' => $recordData,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            if (count($addedRecords) > 0) {
                $zone->incrementSerial();
                $this->syncZoneToPowerDns($zone);
            }

            DB::commit();

            return [
                'added' => $addedRecords,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Bulk delete DNS records from a zone.
     */
    public function bulkDeleteRecords(DnsZone $zone, array $recordIds): array
    {
        $deleted = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($recordIds as $recordId) {
                try {
                    $record = $zone->records()->find($recordId);

                    if (!$record) {
                        $errors[] = [
                            'id' => $recordId,
                            'error' => 'Record not found.',
                        ];
                        continue;
                    }

                    // Prevent deleting SOA records
                    if ($record->type === 'SOA') {
                        $errors[] = [
                            'id' => $recordId,
                            'error' => 'Cannot delete SOA record.',
                        ];
                        continue;
                    }

                    $record->delete();
                    $deleted[] = $recordId;
                } catch (\Exception $e) {
                    $errors[] = [
                        'id' => $recordId,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            if (count($deleted) > 0) {
                $zone->incrementSerial();
                $this->syncZoneToPowerDns($zone);
            }

            DB::commit();

            return [
                'deleted' => $deleted,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Bulk update DNS records.
     */
    public function bulkUpdateRecords(DnsZone $zone, array $updates): array
    {
        $updated = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($updates as $updateData) {
                try {
                    $record = $zone->records()->find($updateData['id']);

                    if (!$record) {
                        $errors[] = [
                            'id' => $updateData['id'],
                            'error' => 'Record not found.',
                        ];
                        continue;
                    }

                    // Prevent updating SOA records type
                    if ($record->type === 'SOA' && isset($updateData['type']) && $updateData['type'] !== 'SOA') {
                        $errors[] = [
                            'id' => $updateData['id'],
                            'error' => 'Cannot change SOA record type.',
                        ];
                        continue;
                    }

                    if (isset($updateData['content'])) {
                        $this->validateRecord(
                            $updateData['type'] ?? $record->type,
                            $updateData['name'] ?? $record->name,
                            $updateData['content']
                        );
                    }

                    $record->update(array_filter([
                        'name' => $updateData['name'] ?? null,
                        'type' => $updateData['type'] ?? null,
                        'content' => $updateData['content'] ?? null,
                        'ttl' => $updateData['ttl'] ?? null,
                        'priority' => $updateData['priority'] ?? null,
                        'disabled' => $updateData['disabled'] ?? null,
                    ], fn($v) => $v !== null));

                    $updated[] = $record->fresh();
                } catch (\Exception $e) {
                    $errors[] = [
                        'id' => $updateData['id'] ?? null,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            if (count($updated) > 0) {
                $zone->incrementSerial();
                $this->syncZoneToPowerDns($zone);
            }

            DB::commit();

            return [
                'updated' => $updated,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Import zone from BIND format.
     */
    public function importZone(DnsZone $zone, string $zoneFileContent, bool $replace = false): array
    {
        $records = $this->parseBindZoneFile($zoneFileContent, $zone->zone_name);
        $imported = [];
        $errors = [];

        DB::beginTransaction();

        try {
            // If replace, delete existing records except SOA
            if ($replace) {
                $zone->records()->where('type', '!=', 'SOA')->delete();
            }

            foreach ($records as $index => $recordData) {
                try {
                    // Skip SOA records (handled separately)
                    if ($recordData['type'] === 'SOA') {
                        continue;
                    }

                    $this->validateRecord($recordData['type'], $recordData['name'], $recordData['content']);

                    // Check for duplicate
                    $existing = $zone->records()
                        ->where('name', $recordData['name'])
                        ->where('type', $recordData['type'])
                        ->where('content', $recordData['content'])
                        ->first();

                    if ($existing && !$replace) {
                        continue;
                    }

                    $record = $zone->records()->create([
                        'name' => $recordData['name'],
                        'type' => $recordData['type'],
                        'content' => $recordData['content'],
                        'ttl' => $recordData['ttl'] ?? 3600,
                        'priority' => $recordData['priority'] ?? null,
                    ]);

                    $imported[] = $record;
                } catch (\Exception $e) {
                    $errors[] = [
                        'line' => $index + 1,
                        'record' => $recordData,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            if (count($imported) > 0) {
                $zone->incrementSerial();
                $this->syncZoneToPowerDns($zone);
            }

            DB::commit();

            return [
                'imported' => count($imported),
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Parse BIND zone file format.
     */
    protected function parseBindZoneFile(string $content, string $zoneName): array
    {
        $records = [];
        $lines = explode("\n", $content);
        $defaultTtl = 3600;
        $origin = $zoneName . '.';
        $lastName = '@';

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, ';')) {
                continue;
            }

            // Handle $TTL directive
            if (preg_match('/^\$TTL\s+(\d+)/i', $line, $matches)) {
                $defaultTtl = (int) $matches[1];
                continue;
            }

            // Handle $ORIGIN directive
            if (preg_match('/^\$ORIGIN\s+(.+)/i', $line, $matches)) {
                $origin = trim($matches[1]);
                continue;
            }

            // Parse record
            // Format: [name] [ttl] [class] type content
            // or: [name] [class] [ttl] type content
            $pattern = '/^(\S+)?\s+(?:(\d+)\s+)?(?:IN\s+)?(\d+)?\s*(A|AAAA|CNAME|MX|TXT|NS|SRV|CAA|PTR|SOA)\s+(.+)$/i';

            if (preg_match($pattern, $line, $matches)) {
                $name = $matches[1] ?: $lastName;
                $ttl1 = $matches[2] ? (int) $matches[2] : null;
                $ttl2 = $matches[3] ? (int) $matches[3] : null;
                $type = strtoupper($matches[4]);
                $content = trim($matches[5]);

                // Determine TTL
                $ttl = $ttl1 ?? $ttl2 ?? $defaultTtl;

                // Normalize name
                if ($name === '@') {
                    $name = '@';
                } elseif ($name === $origin || $name === rtrim($origin, '.')) {
                    $name = '@';
                } elseif (str_ends_with($name, '.' . $origin)) {
                    $name = rtrim(str_replace('.' . $origin, '', $name), '.');
                } elseif (str_ends_with($name, '.')) {
                    // Absolute name - keep as is but remove trailing dot
                    $name = rtrim($name, '.');
                }

                $lastName = $name;

                // Handle MX priority
                $priority = null;
                if ($type === 'MX' && preg_match('/^(\d+)\s+(.+)$/', $content, $mxMatches)) {
                    $priority = (int) $mxMatches[1];
                    $content = $mxMatches[2];
                }

                // Handle SRV records
                $weight = null;
                $port = null;
                if ($type === 'SRV' && preg_match('/^(\d+)\s+(\d+)\s+(\d+)\s+(.+)$/', $content, $srvMatches)) {
                    $priority = (int) $srvMatches[1];
                    $weight = (int) $srvMatches[2];
                    $port = (int) $srvMatches[3];
                    $content = $srvMatches[4];
                }

                // Clean TXT record quotes
                if ($type === 'TXT') {
                    $content = trim($content, '"');
                }

                $records[] = [
                    'name' => $name,
                    'type' => $type,
                    'content' => $content,
                    'ttl' => $ttl,
                    'priority' => $priority,
                    'weight' => $weight,
                    'port' => $port,
                ];
            }
        }

        return $records;
    }

    /**
     * Clone zone records to another zone.
     */
    public function cloneZone(DnsZone $sourceZone, DnsZone $targetZone, bool $replaceExisting = false): array
    {
        $cloned = [];
        $errors = [];

        DB::beginTransaction();

        try {
            // If replace, delete existing records except SOA
            if ($replaceExisting) {
                $targetZone->records()->where('type', '!=', 'SOA')->delete();
            }

            // Get all source records except SOA
            $sourceRecords = $sourceZone->records()
                ->where('type', '!=', 'SOA')
                ->get();

            foreach ($sourceRecords as $sourceRecord) {
                try {
                    // Replace source domain with target domain in content
                    $content = str_replace(
                        $sourceZone->zone_name,
                        $targetZone->zone_name,
                        $sourceRecord->content
                    );

                    // Check for duplicate
                    $existing = $targetZone->records()
                        ->where('name', $sourceRecord->name)
                        ->where('type', $sourceRecord->type)
                        ->first();

                    if ($existing && !$replaceExisting) {
                        continue;
                    }

                    $record = $targetZone->records()->create([
                        'name' => $sourceRecord->name,
                        'type' => $sourceRecord->type,
                        'content' => $content,
                        'ttl' => $sourceRecord->ttl,
                        'priority' => $sourceRecord->priority,
                        'weight' => $sourceRecord->weight,
                        'port' => $sourceRecord->port,
                        'disabled' => $sourceRecord->disabled,
                    ]);

                    $cloned[] = $record;
                } catch (\Exception $e) {
                    $errors[] = [
                        'record' => $sourceRecord->toArray(),
                        'error' => $e->getMessage(),
                    ];
                }
            }

            if (count($cloned) > 0) {
                $targetZone->incrementSerial();
                $this->syncZoneToPowerDns($targetZone);
            }

            DB::commit();

            return [
                'cloned' => count($cloned),
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reset zone to default records.
     */
    public function resetZone(DnsZone $zone, string $serverIp): void
    {
        DB::transaction(function () use ($zone, $serverIp) {
            // Delete all records except SOA
            $zone->records()->where('type', '!=', 'SOA')->delete();

            // Recreate default records
            $this->createDefaultRecords($zone, $serverIp);

            // Update serial
            $zone->incrementSerial();

            // Sync with PowerDNS
            $this->syncZoneToPowerDns($zone);
        });
    }
}
