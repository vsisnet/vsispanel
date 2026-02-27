<?php

declare(strict_types=1);

namespace App\Modules\SSL\Services;

use App\Modules\Domain\Models\Domain;
use App\Modules\Settings\Models\SystemSetting;
use App\Models\User;
use App\Modules\SSL\Models\SslCertificate;
use App\Modules\WebServer\Services\NginxService;
use App\Services\SystemCommandExecutor;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SslService
{
    public function __construct(
        protected SystemCommandExecutor $executor,
        protected NginxService $nginxService
    ) {}

    /**
     * Issue a Let's Encrypt certificate for a domain.
     */
    public function issueLetsEncrypt(Domain $domain): SslCertificate
    {
        // Check if domain already has an active certificate
        $existingCert = SslCertificate::where('domain_id', $domain->id)
            ->where('status', 'active')
            ->first();

        if ($existingCert) {
            throw new RuntimeException("Domain already has an active SSL certificate.");
        }

        // Create pending certificate record
        $certificate = SslCertificate::create([
            'domain_id' => $domain->id,
            'type' => 'lets_encrypt',
            'status' => 'pending',
            'auto_renew' => true,
        ]);

        try {
            // Get list of domains with valid DNS records
            $validDomains = $this->getValidDomainsForCertificate($domain);

            if (empty($validDomains)) {
                throw new RuntimeException("No valid DNS records found for domain: {$domain->name}");
            }

            $email = $this->getLetsEncryptEmail($domain->name);


            // Ensure nginx config exists and is loaded before certbot runs
            $nginxConfigPath = "/etc/nginx/sites-enabled/{$domain->name}.conf";
            if (!file_exists($nginxConfigPath)) {
                // Try to create vhost if it doesn't exist
                $this->nginxService->createVhost($domain);
            } else {
                // Reload nginx to ensure config is active
                $this->nginxService->reload();
            }

            // Small delay to ensure nginx has fully reloaded
            usleep(500000); // 500ms

            // Build certbot arguments
            $certbotArgs = [
                'certonly',
                '--nginx',
                '--non-interactive',
                '--agree-tos',
                '--expand',
                '--email', $email,
            ];

            // Add each valid domain
            foreach ($validDomains as $d) {
                $certbotArgs[] = '-d';
                $certbotArgs[] = $d;
            }

            $result = $this->executor->executeAsRoot('certbot', $certbotArgs);

            if (!$result->success) {
                // Check if cert already exists (not due for renewal)
                $existsCheck = $this->executor->executeAsRoot("test", ["-f", "/etc/letsencrypt/live/{$domain->name}/fullchain.pem"]);
                if (!$existsCheck->success) {
                    throw new RuntimeException("Certbot failed: " . $result->stderr);
                }
                // Cert exists, continue with import
            }

            // Parse certificate paths from certbot output or use standard paths
            $certPath = "/etc/letsencrypt/live/{$domain->name}/fullchain.pem";
            $keyPath = "/etc/letsencrypt/live/{$domain->name}/privkey.pem";

            // Check files as root (letsencrypt dirs are root-only)
            $checkResult = $this->executor->executeAsRoot("test", ["-f", $certPath]);
            $checkKey = $this->executor->executeAsRoot("test", ["-f", $keyPath]);
            if (!$checkResult->success || !$checkKey->success) {
                throw new RuntimeException("Certificate files not found after certbot execution.");
            }

            // Get certificate info
            $certInfo = $this->getCertificateInfo($certPath);

            // Update certificate record
            $certificate->update([
                'status' => 'active',
                'certificate_path' => $certPath,
                'private_key_path' => $keyPath,
                'issuer' => $certInfo['issuer'] ?? "Let's Encrypt",
                'serial_number' => $certInfo['serial'] ?? null,
                'san' => $certInfo['san'] ?? $validDomains,
                'issued_at' => now(),
                'expires_at' => $certInfo['expires_at'] ?? now()->addMonths(3),
            ]);

            // Enable SSL on Nginx
            $this->nginxService->enableSsl($domain, $certPath, $keyPath);

            // Update domain ssl_enabled flag
            $domain->update([
                'ssl_enabled' => true,
                'ssl_expires_at' => $certInfo['expires_at'] ?? now()->addMonths(3),
            ]);

            return $certificate->fresh();
        } catch (\Exception $e) {
            $certificate->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the email address for Let's Encrypt registration.
     * Priority: Settings DB → env/config → empty
     */
    protected function getLetsEncryptEmail(string $domainName = 'localhost'): string
    {
        // 1. Check Settings DB
        $setting = SystemSetting::where('group', 'ssl')
            ->where('key', 'letsencrypt_email')
            ->first();

        if ($setting && !empty($setting->value)) {
            return $setting->value;
        }

        // 2. Fall back to env/config
        $configEmail = config('vsispanel.ssl.letsencrypt_email', '');

        if (!empty($configEmail)) {
            return $configEmail;
        }

        // 3. Fall back to first admin user's email
        $adminUser = User::where('role', 'admin')->first()
            ?? User::orderBy('id')->first();

        if ($adminUser && !empty($adminUser->email)) {
            return $adminUser->email;
        }

        // 4. Last resort: never block SSL issuance
        return 'admin@' . $domainName;
    }

    /**
     * Get list of domains with valid DNS records pointing to this server.
     */
    protected function getValidDomainsForCertificate(Domain $domain): array
    {
        $validDomains = [];
        $domainsToCheck = [
            $domain->name,
            'www.' . $domain->name,
        ];

        // Get server's public IP
        $serverIp = $this->getServerPublicIp();

        foreach ($domainsToCheck as $d) {
            if ($this->checkDnsRecord($d, $serverIp)) {
                $validDomains[] = $d;
            }
        }

        // Always include the main domain - let certbot validate via HTTP challenge
        if (empty($validDomains)) {
            $validDomains[] = $domain->name;
        }

        return $validDomains;
    }

    /**
     * Check if a domain has DNS record pointing to server IP.
     */
    protected function checkDnsRecord(string $domain, ?string $serverIp = null): bool
    {
        // Try to resolve the domain
        $records = @dns_get_record($domain, DNS_A | DNS_AAAA);

        if (empty($records)) {
            return false;
        }

        // If we have server IP, verify it matches
        if ($serverIp) {
            foreach ($records as $record) {
                if (isset($record['ip']) && $record['ip'] === $serverIp) {
                    return true;
                }
                if (isset($record['ipv6']) && $record['ipv6'] === $serverIp) {
                    return true;
                }
            }
            // Domain has DNS but doesn't point to this server - exclude it
            return false;
        }

        return true;
    }

    /**
     * Get the server's public IP address.
     */
    protected function getServerPublicIp(): ?string
    {
        // Try multiple methods to get public IP
        $methods = [
            fn() => @file_get_contents('https://api.ipify.org', false, stream_context_create(['http' => ['timeout' => 5]])),
            fn() => @file_get_contents('https://icanhazip.com', false, stream_context_create(['http' => ['timeout' => 5]])),
            fn() => trim(shell_exec('curl -s --max-time 5 https://api.ipify.org 2>/dev/null') ?? ''),
        ];

        foreach ($methods as $method) {
            try {
                $ip = $method();
                if ($ip && filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                    return trim($ip);
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Upload and install a custom SSL certificate.
     */
    public function uploadCustomCert(
        Domain $domain,
        string $certificate,
        string $privateKey,
        ?string $caBundle = null
    ): SslCertificate {
        // Validate certificate and key match
        $this->validateCertificateKeyPair($certificate, $privateKey);

        // Validate certificate matches domain
        $certInfo = $this->parseCertificateString($certificate);
        $this->validateCertificateDomain($certInfo, $domain->name);

        // Create directory for certificate files (as root since /etc/vsispanel/ssl/ is root-owned)
        $sslDir = config('vsispanel.ssl.custom_cert_path', '/etc/vsispanel/ssl') . '/' . $domain->name;
        $this->executor->executeAsRoot('mkdir', ['-p', $sslDir]);
        $this->executor->executeAsRoot('chmod', ['0700', $sslDir]);

        // Save certificate files via temp files + root copy
        $certPath = $sslDir . '/certificate.pem';
        $keyPath = $sslDir . '/private.key';
        $caPath = $caBundle ? $sslDir . '/ca-bundle.pem' : null;

        $this->writeFileAsRoot($certPath, $certificate, '0644');
        $this->writeFileAsRoot($keyPath, $privateKey, '0600');

        if ($caBundle) {
            $this->writeFileAsRoot($caPath, $caBundle, '0644');
        }

        // Deactivate any existing certificate
        SslCertificate::where('domain_id', $domain->id)
            ->where('status', 'active')
            ->update(['status' => 'revoked']);

        // Create certificate record
        $sslCertificate = SslCertificate::create([
            'domain_id' => $domain->id,
            'type' => 'custom',
            'status' => 'active',
            'certificate_path' => $certPath,
            'private_key_path' => $keyPath,
            'ca_bundle_path' => $caPath,
            'issuer' => $certInfo['issuer'] ?? 'Unknown',
            'serial_number' => $certInfo['serial'] ?? null,
            'san' => $certInfo['san'] ?? [$domain->name],
            'issued_at' => $certInfo['issued_at'] ?? now(),
            'expires_at' => $certInfo['expires_at'] ?? now()->addYear(),
            'auto_renew' => false, // Custom certs don't auto-renew
        ]);

        // Enable SSL on Nginx
        $fullChainPath = $caBundle ? $this->createFullChain($certPath, $caPath) : $certPath;
        $this->nginxService->enableSsl($domain, $fullChainPath, $keyPath);

        // Update domain ssl_enabled flag
        $domain->update(['ssl_enabled' => true]);

        return $sslCertificate;
    }

    /**
     * Renew a Let's Encrypt certificate.
     */
    public function renewCertificate(SslCertificate $certificate): SslCertificate
    {
        if (!$certificate->isLetsEncrypt()) {
            throw new RuntimeException("Only Let's Encrypt certificates can be auto-renewed.");
        }

        $domain = $certificate->domain;

        try {
            $result = $this->executor->executeAsRoot('certbot', [
                'renew',
                '--cert-name', $domain->name,
                '--non-interactive',
            ]);

            if (!$result->success) {
                throw new RuntimeException("Certificate renewal failed: " . $result->stderr);
            }

            // Get updated certificate info
            $certInfo = $this->getCertificateInfo($certificate->certificate_path);

            $certificate->update([
                'expires_at' => $certInfo['expires_at'] ?? now()->addMonths(3),
                'last_renewal_at' => now(),
                'last_error' => null,
            ]);

            // Reload Nginx to apply renewed certificate
            $this->nginxService->reload();

            return $certificate->fresh();
        } catch (\Exception $e) {
            $certificate->update([
                'last_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Revoke and remove a certificate.
     */
    public function revokeCertificate(SslCertificate $certificate): void
    {
        $domain = $certificate->domain;

        // Revoke Let's Encrypt certificate
        if ($certificate->isLetsEncrypt() && $certificate->certificate_path) {
            try {
                $this->executor->executeAsRoot('certbot', [
                    'revoke',
                    '--cert-path', $certificate->certificate_path,
                    '--non-interactive',
                ]);
            } catch (\Exception $e) {
                // Log but continue - certificate may already be revoked
            }
        }

        // Disable SSL on Nginx
        $this->nginxService->disableSsl($domain);

        // Update domain
        $domain->update(['ssl_enabled' => false]);

        // Mark certificate as revoked
        $certificate->markAsRevoked();
    }

    /**
     * Check days until certificate expiry.
     */
    public function checkExpiry(SslCertificate $certificate): int
    {
        if (!$certificate->expires_at) {
            return -1;
        }

        return (int) now()->diffInDays($certificate->expires_at, false);
    }

    /**
     * Get certificate information from file.
     */
    public function getCertificateInfo(string $certPath): array
    {
        // Use executeAsRoot since letsencrypt dirs are root-only
        $checkResult = $this->executor->executeAsRoot('test', ['-f', $certPath]);
        if (!$checkResult->success) {
            throw new RuntimeException("Certificate file not found: {$certPath}");
        }

        $readResult = $this->executor->executeAsRoot('cat', [$certPath]);
        if (!$readResult->success) {
            throw new RuntimeException("Cannot read certificate file: {$certPath}");
        }

        return $this->parseCertificateString($readResult->stdout);
    }

    /**
     * Parse certificate string to extract info.
     */
    protected function parseCertificateString(string $certContent): array
    {
        $cert = openssl_x509_parse($certContent);

        if (!$cert) {
            throw new RuntimeException("Failed to parse certificate.");
        }

        $san = [];
        if (isset($cert['extensions']['subjectAltName'])) {
            $sanString = $cert['extensions']['subjectAltName'];
            preg_match_all('/DNS:([^,\s]+)/', $sanString, $matches);
            $san = $matches[1] ?? [];
        }

        return [
            'subject' => $cert['subject']['CN'] ?? null,
            'issuer' => $cert['issuer']['O'] ?? $cert['issuer']['CN'] ?? 'Unknown',
            'serial' => $cert['serialNumberHex'] ?? null,
            'san' => $san,
            'issued_at' => isset($cert['validFrom_time_t'])
                ? \Carbon\Carbon::createFromTimestamp($cert['validFrom_time_t'])
                : null,
            'expires_at' => isset($cert['validTo_time_t'])
                ? \Carbon\Carbon::createFromTimestamp($cert['validTo_time_t'])
                : null,
        ];
    }

    /**
     * Validate that certificate and private key match.
     */
    protected function validateCertificateKeyPair(string $certificate, string $privateKey): void
    {
        $cert = openssl_x509_read($certificate);
        $key = openssl_pkey_get_private($privateKey);

        if (!$cert || !$key) {
            throw new RuntimeException("Invalid certificate or private key format.");
        }

        if (!openssl_x509_check_private_key($cert, $key)) {
            throw new RuntimeException("Certificate and private key do not match.");
        }
    }

    /**
     * Validate certificate covers the domain.
     */
    protected function validateCertificateDomain(array $certInfo, string $domainName): void
    {
        $validDomains = array_merge(
            [$certInfo['subject'] ?? ''],
            $certInfo['san'] ?? []
        );

        $matches = false;
        foreach ($validDomains as $certDomain) {
            if ($this->domainMatchesCertificate($domainName, $certDomain)) {
                $matches = true;
                break;
            }
        }

        if (!$matches) {
            throw new RuntimeException(
                "Certificate does not cover domain '{$domainName}'. " .
                "Valid domains: " . implode(', ', $validDomains)
            );
        }
    }

    /**
     * Check if domain matches certificate domain (supports wildcards).
     */
    protected function domainMatchesCertificate(string $domain, string $certDomain): bool
    {
        if ($domain === $certDomain) {
            return true;
        }

        // Handle wildcard certificates
        if (str_starts_with($certDomain, '*.')) {
            $wildcardBase = substr($certDomain, 2);
            $domainParts = explode('.', $domain);
            array_shift($domainParts);
            $domainBase = implode('.', $domainParts);

            return $wildcardBase === $domainBase;
        }

        return false;
    }



    /**
     * Write content to a file as root using a temp file.
     */
    protected function writeFileAsRoot(string $path, string $content, string $mode = '0644'): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ssl_');
        file_put_contents($tmpFile, $content);
        $this->executor->executeAsRoot('cp', [$tmpFile, $path]);
        $this->executor->executeAsRoot('chmod', [$mode, $path]);
        @unlink($tmpFile);
    }

    /**
     * Create a full chain file from certificate and CA bundle.
     */
    protected function createFullChain(string $certPath, string $caPath): string
    {
        $fullChainPath = dirname($certPath) . '/fullchain.pem';

        // Read cert files as root (may be in root-owned directories like /etc/vsispanel/ssl/)
        $certResult = $this->executor->executeAsRoot('cat', [$certPath]);
        $caResult = $this->executor->executeAsRoot('cat', [$caPath]);

        if (!$certResult->success || !$caResult->success) {
            throw new RuntimeException("Cannot read certificate files for full chain creation.");
        }

        $chainContent = $certResult->stdout . "\n" . $caResult->stdout;

        // Write full chain file as root
        $tmpFile = tempnam(sys_get_temp_dir(), 'ssl_');
        file_put_contents($tmpFile, $chainContent);
        $this->executor->executeAsRoot('cp', [$tmpFile, $fullChainPath]);
        $this->executor->executeAsRoot('chmod', ['0644', $fullChainPath]);
        @unlink($tmpFile);

        return $fullChainPath;
    }

    /**
     * Get certificates expiring within specified days.
     */
    public function getExpiringCertificates(int $days = 30): \Illuminate\Database\Eloquent\Collection
    {
        return SslCertificate::expiringSoon($days)
            ->autoRenewable()
            ->with('domain')
            ->get();
    }

    /**
     * Process auto-renewal for expiring certificates.
     */
    public function processAutoRenewals(int $daysBeforeExpiry = 30): array
    {
        $results = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'renewed' => [],
            'errors' => [],
        ];

        $certificates = $this->getExpiringCertificates($daysBeforeExpiry);
        $results['processed'] = $certificates->count();

        foreach ($certificates as $certificate) {
            try {
                $this->renewCertificate($certificate);
                $results['succeeded']++;
                $results['renewed'][] = [
                    'id' => $certificate->id,
                    'domain' => $certificate->domain->name,
                ];
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'id' => $certificate->id,
                    'domain' => $certificate->domain->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
