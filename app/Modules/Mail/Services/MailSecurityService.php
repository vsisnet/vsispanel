<?php

declare(strict_types=1);

namespace App\Modules\Mail\Services;

use App\Modules\DNS\Services\PowerDnsService;
use App\Services\CommandResult;
use App\Services\SystemCommandExecutor;
use Illuminate\Support\Facades\File;
use RuntimeException;

class MailSecurityService
{
    protected string $dkimKeyDir;
    protected string $dkimSelector;

    public function __construct(
        protected SystemCommandExecutor $executor,
        protected ?PowerDnsService $dnsService = null
    ) {
        $this->dkimKeyDir = config('vsispanel.mail.dkim_key_dir', '/etc/opendkim/keys');
        $this->dkimSelector = config('vsispanel.mail.dkim_selector', 'mail');
    }

    /**
     * Generate SPF record value.
     */
    public function generateSPF(string $domain, string $serverIp, array $options = []): string
    {
        $mechanisms = ['mx', 'a'];

        // Add IPv4
        if (filter_var($serverIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $mechanisms[] = "ip4:{$serverIp}";
        }

        // Add IPv6 if provided
        if (isset($options['ipv6']) && filter_var($options['ipv6'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $mechanisms[] = "ip6:{$options['ipv6']}";
        }

        // Add include if provided (e.g., for third-party email services)
        if (isset($options['include']) && is_array($options['include'])) {
            foreach ($options['include'] as $include) {
                $mechanisms[] = "include:{$include}";
            }
        }

        // SPF policy: ~all (soft fail) is recommended for initial setup
        $policy = $options['policy'] ?? '~all';

        return 'v=spf1 ' . implode(' ', $mechanisms) . ' ' . $policy;
    }

    /**
     * Generate DKIM keys and return DNS record info.
     */
    public function generateDKIM(string $domain): array
    {
        $domainKeyDir = "{$this->dkimKeyDir}/{$domain}";
        $selector = $this->dkimSelector;

        // Create key directory
        if (!File::isDirectory($domainKeyDir)) {
            $this->executor->executeAsRoot('mkdir', ['-p', $domainKeyDir]);
        }

        $privateKeyPath = "{$domainKeyDir}/{$selector}.private";
        $txtRecordPath = "{$domainKeyDir}/{$selector}.txt";

        // Try opendkim-genkey first, fallback to openssl
        $checkResult = $this->executor->executeAsRoot('which', ['opendkim-genkey']);
        $useOpendkim = $checkResult->success && !empty(trim($checkResult->stdout));

        if ($useOpendkim) {
            $result = $this->executor->executeAsRoot('opendkim-genkey', [
                '-b', '2048',
                '-d', $domain,
                '-D', $domainKeyDir,
                '-s', $selector,
                '-v',
            ]);

            if (!$result->success) {
                throw new RuntimeException("Failed to generate DKIM key: " . $result->stderr);
            }

            // Set correct permissions
            $this->executor->executeAsRoot('chown', ['-R', 'opendkim:opendkim', $domainKeyDir]);
            $this->executor->executeAsRoot('chmod', ['600', $privateKeyPath]);

            // Read the generated TXT record
            $txtResult = $this->executor->executeAsRoot("cat", [$txtRecordPath]);
            $txtContent = $txtResult->success ? $txtResult->stdout : "";

            preg_match('/"([^"]+)"/', $txtContent, $matches);
            $publicKeyRecord = isset($matches[1])
                ? str_replace(["\n", "\t", ' '], '', $matches[1])
                : '';

            preg_match('/p=([^;]+)/', $publicKeyRecord, $keyMatch);
            $publicKey = $keyMatch[1] ?? '';
            $privResult = $this->executor->executeAsRoot("cat", [$privateKeyPath]);
            $privateKey = $privResult->success ? trim($privResult->stdout) : "";
        } else {
            // Fallback: generate DKIM keys using openssl
            $this->executor->executeAsRoot('openssl', [
                'genrsa', '-out', $privateKeyPath, '2048',
            ]);
            $this->executor->executeAsRoot('chmod', ['600', $privateKeyPath]);

            // Extract public key
            $pubResult = $this->executor->executeAsRoot('openssl', [
                'rsa', '-in', $privateKeyPath, '-pubout', '-outform', 'PEM',
            ]);

            if (!$pubResult->success) {
                throw new RuntimeException("Failed to extract DKIM public key: " . $pubResult->stderr);
            }

            $pubPem = trim($pubResult->stdout);
            // Remove PEM headers and newlines to get raw base64
            $publicKey = str_replace([
                '-----BEGIN PUBLIC KEY-----',
                '-----END PUBLIC KEY-----',
                "\n", "\r",
            ], '', $pubPem);

            $privResult = $this->executor->executeAsRoot("cat", [$privateKeyPath]);
            $privateKey = $privResult->success ? trim($privResult->stdout) : "";

            // Write a TXT record file for reference
            $txtContent = "{$selector}._domainkey IN TXT \"v=DKIM1; k=rsa; p={$publicKey}\"";
            $this->executor->executeAsRoot('bash', ['-c', 'echo ' . escapeshellarg($txtContent) . ' > ' . escapeshellarg($txtRecordPath)]);

            // Try to set opendkim ownership, ignore if user doesn't exist
            $this->executor->executeAsRoot('chown', ['-R', 'root:root', $domainKeyDir]);
        }

        // Add to OpenDKIM key table
        $this->addToKeyTable($domain, $selector, $privateKeyPath);

        // Add to OpenDKIM signing table
        $this->addToSigningTable($domain);

        // Reload OpenDKIM
        $this->reloadDkim();

        return [
            'selector' => $selector,
            'private_key_path' => $privateKeyPath,
            'private_key' => $privateKey,
            'public_key' => $publicKey,
            'dns_record_name' => "{$selector}._domainkey.{$domain}",
            'dns_record_type' => 'TXT',
            'dns_record_value' => $this->formatDkimRecord($domain, $selector, $publicKey),
        ];
    }

    /**
     * Get existing DKIM key for a domain.
     */
    public function getDKIM(string $domain): ?array
    {
        $selector = $this->dkimSelector;
        $domainKeyDir = "{$this->dkimKeyDir}/{$domain}";
        $privateKeyPath = "{$domainKeyDir}/{$selector}.private";
        $txtRecordPath = "{$domainKeyDir}/{$selector}.txt";

        if (!File::exists($privateKeyPath)) {
            return null;
        }

        // Read the TXT record file
        $txtResult2 = $this->executor->executeAsRoot('cat', [$txtRecordPath]);
        $txtContent = $txtResult2->success ? $txtResult2->stdout : '';
        preg_match('/p=([^;"\s]+)/', $txtContent, $keyMatch);
        $publicKey = $keyMatch[1] ?? '';

        return [
            'selector' => $selector,
            'private_key_path' => $privateKeyPath,
            'public_key' => $publicKey,
            'dns_record_name' => "{$selector}._domainkey.{$domain}",
            'dns_record_type' => 'TXT',
            'dns_record_value' => $this->formatDkimRecord($domain, $selector, $publicKey),
        ];
    }

    /**
     * Delete DKIM keys for a domain.
     */
    public function deleteDKIM(string $domain): void
    {
        $domainKeyDir = "{$this->dkimKeyDir}/{$domain}";

        // Remove from key table
        $this->removeFromKeyTable($domain);

        // Remove from signing table
        $this->removeFromSigningTable($domain);

        // Remove key directory
        if (File::isDirectory($domainKeyDir)) {
            $this->executor->executeAsRoot('rm', ['-rf', $domainKeyDir]);
        }

        $this->reloadDkim();
    }

    /**
     * Generate DMARC record value.
     */
    public function generateDMARC(string $domain, string $adminEmail, array $options = []): string
    {
        $policy = $options['policy'] ?? 'none'; // none, quarantine, reject
        $subdomainPolicy = $options['subdomain_policy'] ?? $policy;
        $percentage = $options['percentage'] ?? 100;
        $reportFormat = $options['report_format'] ?? 'afrf';

        // Build DMARC record
        $parts = [
            'v=DMARC1',
            "p={$policy}",
        ];

        // Add subdomain policy if different from main policy
        if ($subdomainPolicy !== $policy) {
            $parts[] = "sp={$subdomainPolicy}";
        }

        // Add percentage if not 100%
        if ($percentage !== 100) {
            $parts[] = "pct={$percentage}";
        }

        // Add aggregate report URI
        $parts[] = "rua=mailto:{$adminEmail}";

        // Add forensic report URI (optional)
        if (!empty($options['forensic_email'])) {
            $parts[] = "ruf=mailto:{$options['forensic_email']}";
        }

        // ADKIM and ASPF alignment modes
        $parts[] = "adkim=" . ($options['adkim'] ?? 'r'); // r=relaxed, s=strict
        $parts[] = "aspf=" . ($options['aspf'] ?? 'r');

        // Report format
        $parts[] = "rf={$reportFormat}";

        // Report interval (default 86400 = 24 hours)
        $parts[] = "ri=" . ($options['report_interval'] ?? 86400);

        return implode('; ', $parts);
    }

    /**
     * Create all mail security DNS records for a domain.
     */
    public function setupMailSecurity(string $domain, string $serverIp, string $adminEmail): array
    {
        $records = [];

        // Generate SPF
        $spfValue = $this->generateSPF($domain, $serverIp);
        $records['spf'] = [
            'name' => $domain,
            'type' => 'TXT',
            'content' => $spfValue,
        ];

        // Generate DKIM
        $dkim = $this->generateDKIM($domain);
        $records['dkim'] = [
            'name' => $dkim['dns_record_name'],
            'type' => 'TXT',
            'content' => $dkim['dns_record_value'],
        ];

        // Generate DMARC
        $dmarcValue = $this->generateDMARC($domain, $adminEmail);
        $records['dmarc'] = [
            'name' => "_dmarc.{$domain}",
            'type' => 'TXT',
            'content' => $dmarcValue,
        ];

        return $records;
    }

    /**
     * Verify SPF record for a domain.
     */
    public function verifySPF(string $domain): array
    {
        $result = $this->executor->execute('dig', ['+short', 'TXT', $domain]);

        $records = [];
        $hasSpf = false;

        if ($result->success) {
            $lines = explode("\n", $result->stdout);
            foreach ($lines as $line) {
                $line = trim($line, '"');
                if (str_starts_with($line, 'v=spf1')) {
                    $hasSpf = true;
                    $records[] = $line;
                }
            }
        }

        return [
            'valid' => $hasSpf,
            'records' => $records,
        ];
    }

    /**
     * Verify DKIM record for a domain.
     */
    public function verifyDKIM(string $domain, ?string $selector = null): array
    {
        $selector = $selector ?? $this->dkimSelector;
        $recordName = "{$selector}._domainkey.{$domain}";

        $result = $this->executor->execute('dig', ['+short', 'TXT', $recordName]);

        $hasKey = false;
        $publicKey = null;

        if ($result->success && !empty(trim($result->stdout))) {
            $content = trim($result->stdout, '"');
            if (str_contains($content, 'p=')) {
                $hasKey = true;
                preg_match('/p=([^;]+)/', $content, $matches);
                $publicKey = $matches[1] ?? null;
            }
        }

        return [
            'valid' => $hasKey,
            'selector' => $selector,
            'public_key' => $publicKey,
            'record_name' => $recordName,
        ];
    }

    /**
     * Verify DMARC record for a domain.
     */
    public function verifyDMARC(string $domain): array
    {
        $recordName = "_dmarc.{$domain}";
        $result = $this->executor->execute('dig', ['+short', 'TXT', $recordName]);

        $hasDmarc = false;
        $policy = null;
        $record = null;

        if ($result->success) {
            $content = trim($result->stdout, '"');
            if (str_starts_with($content, 'v=DMARC1')) {
                $hasDmarc = true;
                $record = $content;

                preg_match('/p=([^;]+)/', $content, $matches);
                $policy = $matches[1] ?? null;
            }
        }

        return [
            'valid' => $hasDmarc,
            'policy' => $policy,
            'record' => $record,
        ];
    }

    /**
     * Format DKIM record for DNS.
     */
    protected function formatDkimRecord(string $domain, string $selector, string $publicKey): string
    {
        // DKIM record format: v=DKIM1; k=rsa; p=<public_key>
        return "v=DKIM1; k=rsa; p={$publicKey}";
    }

    /**
     * Add domain to OpenDKIM key table.
     */
    protected function addToKeyTable(string $domain, string $selector, string $keyPath): void
    {
        $keyTableFile = '/etc/opendkim/KeyTable';
        $this->ensureFileExists($keyTableFile);

        $entry = "{$selector}._domainkey.{$domain} {$domain}:{$selector}:{$keyPath}";

        // Check if already exists
        $readResult = $this->executor->executeAsRoot('cat', [$keyTableFile]);
        $content = $readResult->success ? $readResult->stdout : '';
        if (!str_contains($content, "{$selector}._domainkey.{$domain}")) {
            $this->executor->executeAsRoot('bash', ['-c', "echo " . escapeshellarg($entry) . " >> " . escapeshellarg($keyTableFile)]);
        }
    }

    /**
     * Remove domain from OpenDKIM key table.
     */
    protected function removeFromKeyTable(string $domain): void
    {
        $keyTableFile = '/etc/opendkim/KeyTable';
        if (!File::exists($keyTableFile)) {
            return;
        }

        $readResult = $this->executor->executeAsRoot('cat', [$keyTableFile]);
        $content = $readResult->success ? $readResult->stdout : '';
        $lines = explode("\n", $content);
        $filtered = array_filter($lines, fn($line) => !str_contains($line, $domain));
        $this->executor->executeAsRoot('bash', ['-c', 'echo ' . escapeshellarg(implode("\n", $filtered)) . ' > ' . escapeshellarg($keyTableFile)]);
    }

    /**
     * Add domain to OpenDKIM signing table.
     */
    protected function addToSigningTable(string $domain): void
    {
        $signingTableFile = '/etc/opendkim/SigningTable';
        $this->ensureFileExists($signingTableFile);

        $selector = $this->dkimSelector;
        $entry = "*@{$domain} {$selector}._domainkey.{$domain}";

        // Check if already exists
        $readResult = $this->executor->executeAsRoot('cat', [$signingTableFile]);
        $content = $readResult->success ? $readResult->stdout : '';
        if (!str_contains($content, "*@{$domain}")) {
            $this->executor->executeAsRoot('bash', ['-c', "echo " . escapeshellarg($entry) . " >> " . escapeshellarg($signingTableFile)]);
        }
    }

    /**
     * Remove domain from OpenDKIM signing table.
     */
    protected function removeFromSigningTable(string $domain): void
    {
        $signingTableFile = '/etc/opendkim/SigningTable';
        if (!File::exists($signingTableFile)) {
            return;
        }

        $readResult = $this->executor->executeAsRoot('cat', [$signingTableFile]);
        $content = $readResult->success ? $readResult->stdout : '';
        $lines = explode("\n", $content);
        $filtered = array_filter($lines, fn($line) => !str_contains($line, "@{$domain}"));
        $this->executor->executeAsRoot('bash', ['-c', 'echo ' . escapeshellarg(implode("\n", $filtered)) . ' > ' . escapeshellarg($signingTableFile)]);
    }

    /**
     * Reload OpenDKIM.
     */
    protected function reloadDkim(): CommandResult
    {
        return $this->executor->executeAsRoot('systemctl', ['reload', 'opendkim']);
    }

    /**
     * Ensure a file exists with correct permissions.
     */
    protected function ensureFileExists(string $file): void
    {
        if (!File::exists($file)) {
            $this->executor->executeAsRoot('touch', [$file]);
            $this->executor->executeAsRoot('chown', ['opendkim:opendkim', $file]);
            $this->executor->executeAsRoot('chmod', ['640', $file]);
        }
    }

    /**
     * Get OpenDKIM status.
     */
    public function getDkimStatus(): array
    {
        $statusResult = $this->executor->executeAsRoot('systemctl', ['is-active', 'opendkim']);
        $isRunning = $statusResult->success && trim($statusResult->stdout) === 'active';

        return [
            'running' => $isRunning,
            'selector' => $this->dkimSelector,
            'key_dir' => $this->dkimKeyDir,
        ];
    }
}
