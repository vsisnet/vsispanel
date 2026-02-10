<?php

declare(strict_types=1);

namespace App\Modules\Domain\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Domain\Models\Domain;
use App\Modules\Domain\Models\Subdomain;
use App\Modules\DNS\Models\DnsZone;
use App\Modules\DNS\Services\PowerDnsService;
use App\Modules\FTP\Models\FtpAccount;
use App\Modules\SSL\Models\SslCertificate;
use App\Modules\SSL\Services\SslService;
use App\Modules\WebServer\Services\NginxService;
use App\Modules\WebServer\Services\PhpFpmService;
use App\Services\SystemCommandExecutor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DomainService
{
    public function __construct(
        protected SystemCommandExecutor $executor,
        protected ?NginxService $nginxService = null,
        protected ?PhpFpmService $phpFpmService = null,
        protected ?SslService $sslService = null,
        protected ?PowerDnsService $dnsService = null
    ) {}

    /**
     * Create a new domain for a user.
     */
    public function create(User $user, array $data): Domain
    {
        return DB::transaction(function () use ($user, $data) {
            $username = $this->getUsername($user);
            $domainName = strtolower($data['name']);

            // Get subscription_id from data or user's active subscription
            $subscriptionId = $data['subscription_id'] ?? $user->subscriptions()
                ->where('status', 'active')
                ->first()?->id;

            if (!$subscriptionId) {
                throw new \RuntimeException('User must have an active subscription to create a domain.');
            }

            // Create domain record
            $domain = Domain::create([
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'name' => $domainName,
                'php_version' => $data['php_version'] ?? config('webserver.default_php_version', '8.3'),
                'status' => 'pending',
                'ssl_enabled' => false,
                'is_main' => $data['is_main'] ?? false,
                'web_server_type' => $data['web_server_type'] ?? 'nginx',
            ]);

            // Create directory structure
            $this->createDirectoryStructure($domain, $username);

            // Create default index.html
            $this->createDefaultIndex($domain);

            // Create PHP-FPM pool for this domain
            if ($this->phpFpmService) {
                try {
                    $this->phpFpmService->createDomainPool($domain);
                } catch (\Exception $e) {
                    Log::channel('commands')->warning('Failed to create PHP-FPM pool', [
                        'domain' => $domainName,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue - pool can be created later when updating PHP settings
                }
            }

            // Create Nginx virtual host
            if ($this->nginxService && $domain->web_server_type === 'nginx') {
                try {
                    $this->nginxService->createVhost($domain);
                } catch (\Exception $e) {
                    Log::channel('commands')->warning('Failed to create Nginx vhost', [
                        'domain' => $domainName,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue - domain is created, vhost can be created later
                }
            }

            // Update status to active
            $domain->update(['status' => 'active']);

            // Create DNS zone if requested
            if (($data['create_dns'] ?? false) && $this->dnsService) {
                try {
                    $serverIp = trim(shell_exec('hostname -I') ?: '');
                    $serverIp = explode(' ', $serverIp)[0] ?? '127.0.0.1';
                    $this->dnsService->createZone($domain, $serverIp);
                    Log::channel('commands')->info('DNS zone created for domain', ['domain' => $domainName]);
                } catch (\Throwable $e) {
                    Log::channel('commands')->warning('Failed to create DNS zone', [
                        'domain' => $domainName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Issue SSL certificate if requested
            if (($data['auto_ssl'] ?? false) && $this->sslService) {
                try {
                    $this->sslService->issueLetsEncrypt($domain);
                    Log::channel('commands')->info('SSL certificate issued for domain', ['domain' => $domainName]);
                } catch (\Throwable $e) {
                    Log::channel('commands')->warning('Failed to issue SSL certificate', [
                        'domain' => $domainName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::channel('commands')->info('Domain created', [
                'domain' => $domainName,
                'user' => $username,
            ]);

            return $domain->fresh();
        });
    }

    /**
     * Update a domain.
     */
    public function update(Domain $domain, array $data): Domain
    {
        $oldPhpVersion = $domain->php_version;

        $domain->update([
            'php_version' => $data['php_version'] ?? $domain->php_version,
            'web_server_type' => $data['web_server_type'] ?? $domain->web_server_type,
            'is_main' => $data['is_main'] ?? $domain->is_main,
        ]);

        // If PHP version changed, update nginx config
        if (isset($data['php_version']) && $data['php_version'] !== $oldPhpVersion) {
            Log::channel('commands')->info('Domain PHP version changed', [
                'domain' => $domain->name,
                'from' => $oldPhpVersion,
                'to' => $data['php_version'],
            ]);

            // Update Nginx vhost configuration
            if ($this->nginxService && $domain->web_server_type === 'nginx') {
                try {
                    $this->nginxService->updateVhost($domain);
                } catch (\Exception $e) {
                    Log::channel('commands')->warning('Failed to update Nginx vhost', [
                        'domain' => $domain->name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $domain->fresh();
    }

    /**
     * Delete a domain (archive, not permanent).
     */
    public function delete(Domain $domain): void
    {
        DB::transaction(function () use ($domain) {
            $username = $this->getUsername($domain->user);

            // Remove Nginx vhost
            if ($this->nginxService && $domain->web_server_type === 'nginx') {
                try {
                    $this->nginxService->deleteVhost($domain);
                } catch (\Throwable $e) {
                    Log::channel('commands')->warning('Failed to delete Nginx vhost', [
                        'domain' => $domain->name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Delete PHP-FPM pool for this domain
            if ($this->phpFpmService) {
                try {
                    $this->phpFpmService->deleteDomainPool($domain);
                } catch (\Throwable $e) {
                    Log::channel('commands')->warning('Failed to delete PHP-FPM pool', [
                        'domain' => $domain->name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Revoke and clean up SSL certificate
            $this->cleanupSslCertificate($domain);

            // Delete DNS zone
            $this->cleanupDnsZone($domain);

            // Delete FTP accounts for this domain
            $this->cleanupFtpAccounts($domain);

            // Archive document root (move to trash)
            $this->archiveDomainDirectory($domain, $username);

            // Delete subdomains first
            $domain->subdomains()->delete();

            // Soft delete domain
            $domain->delete();

            Log::channel('commands')->info('Domain deleted', [
                'domain' => $domain->name,
                'user' => $username,
            ]);
        });
    }

    /**
     * Permanently delete a domain.
     */
    public function forceDelete(Domain $domain): void
    {
        DB::transaction(function () use ($domain) {
            $username = $this->getUsername($domain->user);

            // Remove Nginx vhost
            if ($this->nginxService && $domain->web_server_type === 'nginx') {
                try {
                    $this->nginxService->deleteVhost($domain);
                } catch (\Throwable $e) {
                    Log::channel('commands')->warning('Failed to delete Nginx vhost', [
                        'domain' => $domain->name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Delete PHP-FPM pool
            if ($this->phpFpmService) {
                try {
                    $this->phpFpmService->deleteDomainPool($domain);
                } catch (\Throwable $e) {
                    Log::channel('commands')->warning('Failed to delete PHP-FPM pool', [
                        'domain' => $domain->name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Revoke and clean up SSL certificate
            $this->cleanupSslCertificate($domain);

            // Delete DNS zone
            $this->cleanupDnsZone($domain);

            // Delete FTP accounts for this domain
            $this->cleanupFtpAccounts($domain);

            // Remove directory permanently
            $domainPath = "/home/{$username}/domains/{$domain->name}";
            $this->executor->executeAsRoot('rm', ['-rf', $domainPath]);

            // Force delete from database
            $domain->subdomains()->forceDelete();
            $domain->forceDelete();

            Log::channel('commands')->info('Domain permanently deleted', [
                'domain' => $domain->name,
                'user' => $username,
            ]);
        });
    }

    /**
     * Suspend a domain.
     */
    public function suspend(Domain $domain, string $reason = ''): void
    {
        $domain->update(['status' => 'suspended']);

        Log::channel('commands')->info('Domain suspended', [
            'domain' => $domain->name,
            'reason' => $reason,
        ]);
    }

    /**
     * Unsuspend a domain.
     */
    public function unsuspend(Domain $domain): void
    {
        $domain->update(['status' => 'active']);

        Log::channel('commands')->info('Domain unsuspended', [
            'domain' => $domain->name,
        ]);
    }

    /**
     * Change PHP version for a domain.
     */
    public function changePHPVersion(Domain $domain, string $version): void
    {
        $oldVersion = $domain->php_version;

        // Use PhpFpmService to handle the version switch with pool migration
        if ($this->phpFpmService && $oldVersion !== $version) {
            try {
                $this->phpFpmService->switchDomainVersion($domain, $oldVersion, $version);
            } catch (\Exception $e) {
                Log::channel('commands')->warning('Failed to switch PHP version via PhpFpmService', [
                    'domain' => $domain->name,
                    'error' => $e->getMessage(),
                ]);
                // Fallback: just update the domain record
                $domain->update(['php_version' => $version]);
            }
        } else {
            $domain->update(['php_version' => $version]);
        }

        Log::channel('commands')->info('Domain PHP version changed', [
            'domain' => $domain->name,
            'from' => $oldVersion,
            'to' => $version,
        ]);
    }

    /**
     * Create a subdomain.
     */
    public function createSubdomain(Domain $domain, array $data): Subdomain
    {
        return DB::transaction(function () use ($domain, $data) {
            $subdomain = Subdomain::create([
                'domain_id' => $domain->id,
                'name' => strtolower($data['name']),
                'php_version' => $data['php_version'] ?? $domain->php_version,
                'status' => 'active',
                'ssl_enabled' => false,
            ]);

            // Create subdomain directory
            $this->createSubdomainDirectory($subdomain, $domain);

            Log::channel('commands')->info('Subdomain created', [
                'subdomain' => $subdomain->full_name,
                'domain' => $domain->name,
            ]);

            return $subdomain->fresh();
        });
    }

    /**
     * Delete a subdomain.
     */
    public function deleteSubdomain(Subdomain $subdomain): void
    {
        DB::transaction(function () use ($subdomain) {
            $domain = $subdomain->domain;
            $username = $this->getUsername($domain->user);

            // Archive subdomain directory
            $subdomainPath = "/home/{$username}/domains/{$domain->name}/subdomains/{$subdomain->name}";
            $trashPath = "/home/{$username}/.trash/subdomains/{$subdomain->name}_" . now()->format('YmdHis');

            $this->executor->executeAsRoot('mkdir', ['-p', dirname($trashPath)]);
            $this->executor->executeAsRoot('mv', [$subdomainPath, $trashPath]);

            $subdomain->delete();

            Log::channel('commands')->info('Subdomain deleted', [
                'subdomain' => $subdomain->full_name,
            ]);
        });
    }

    /**
     * Get disk usage for a domain.
     */
    public function getDiskUsage(Domain $domain): int
    {
        $username = $this->getUsername($domain->user);
        $domainPath = "/home/{$username}/domains/{$domain->name}";

        $result = $this->executor->execute('du', ['-sb', $domainPath]);

        if ($result->success) {
            $parts = explode("\t", trim($result->stdout));
            $bytes = (int) ($parts[0] ?? 0);

            $domain->update(['disk_used' => $bytes]);

            return $bytes;
        }

        return 0;
    }

    /**
     * Get username from user.
     */
    protected function getUsername(User $user): string
    {
        return $user->username ?? Str::slug($user->name, '_');
    }

    /**
     * Create directory structure for a domain.
     */
    protected function createDirectoryStructure(Domain $domain, string $username): void
    {
        $basePath = "/home/{$username}/domains/{$domain->name}";

        $directories = [
            "{$basePath}/public_html",
            "{$basePath}/logs",
            "{$basePath}/tmp",
            "{$basePath}/ssl",
            "{$basePath}/subdomains",
        ];

        foreach ($directories as $dir) {
            $this->executor->executeAsRoot('mkdir', ['-p', $dir]);
        }

        // Set ownership
        $this->executor->executeAsRoot('chown', ['-R', "{$username}:{$username}", $basePath]);

        // Set permissions
        $this->executor->executeAsRoot('chmod', ['-R', '755', $basePath]);

        // Logs directory needs www-data group for Nginx workers to write access/error logs
        $this->executor->executeAsRoot('chown', ["{$username}:www-data", "{$basePath}/logs"]);
        $this->executor->executeAsRoot('chmod', ['770', "{$basePath}/logs"]);

        // Update domain paths
        $domain->update([
            'document_root' => "{$basePath}/public_html",
            'access_log' => "{$basePath}/logs/access.log",
            'error_log' => "{$basePath}/logs/error.log",
        ]);
    }

    /**
     * Create default index.html file.
     */
    protected function createDefaultIndex(Domain $domain): void
    {
        $content = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {$domain->name}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #1a5276 0%, #2ecc71 100%);
            color: white;
        }
        .container {
            text-align: center;
            padding: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }
        h1 { font-size: 2.5rem; margin-bottom: 0.5rem; }
        p { font-size: 1.2rem; opacity: 0.9; }
        .domain { font-weight: bold; color: #2ecc71; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to <span class="domain">{$domain->name}</span></h1>
        <p>Your website is ready to be configured.</p>
        <p style="font-size: 0.9rem; margin-top: 2rem; opacity: 0.7;">Powered by VSISPanel</p>
    </div>
</body>
</html>
HTML;

        $indexPath = $domain->document_root_path . '/index.html';
        $username = $this->getUsername($domain->user);

        // Write file as root then change ownership
        $tempFile = "/tmp/index_" . Str::random(8) . ".html";
        file_put_contents($tempFile, $content);

        $this->executor->executeAsRoot('cp', [$tempFile, $indexPath]);
        $this->executor->executeAsRoot('chown', ["{$username}:{$username}", $indexPath]);
        $this->executor->executeAsRoot('chmod', ['644', $indexPath]);

        unlink($tempFile);
    }

    /**
     * Clean up SSL certificate for a domain.
     */
    protected function cleanupSslCertificate(Domain $domain): void
    {
        try {
            $certificate = $domain->sslCertificate;
            if (!$certificate) {
                return;
            }

            if ($this->sslService) {
                $this->sslService->revokeCertificate($certificate);
            }

            // Delete certbot files
            $certbotLivePath = "/etc/letsencrypt/live/{$domain->name}";
            $certbotRenewalPath = "/etc/letsencrypt/renewal/{$domain->name}.conf";
            $certbotArchivePath = "/etc/letsencrypt/archive/{$domain->name}";

            $this->executor->executeAsRoot('rm', ['-rf', $certbotLivePath]);
            $this->executor->executeAsRoot('rm', ['-f', $certbotRenewalPath]);
            $this->executor->executeAsRoot('rm', ['-rf', $certbotArchivePath]);

            $certificate->delete();

            Log::channel('commands')->info('SSL certificate cleaned up', [
                'domain' => $domain->name,
            ]);
        } catch (\Throwable $e) {
            Log::channel('commands')->warning('Failed to clean up SSL certificate', [
                'domain' => $domain->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clean up DNS zone for a domain.
     */
    protected function cleanupDnsZone(Domain $domain): void
    {
        try {
            $zone = $domain->dnsZone;
            if (!$zone) {
                return;
            }

            if ($this->dnsService) {
                $this->dnsService->deleteZone($zone);
            } else {
                // Fallback: delete records and zone from DB
                $zone->records()->delete();
                $zone->delete();
            }

            Log::channel('commands')->info('DNS zone cleaned up', [
                'domain' => $domain->name,
            ]);
        } catch (\Throwable $e) {
            Log::channel('commands')->warning('Failed to clean up DNS zone', [
                'domain' => $domain->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clean up FTP accounts for a domain.
     */
    protected function cleanupFtpAccounts(Domain $domain): void
    {
        try {
            $ftpAccounts = FtpAccount::where('domain_id', $domain->id)->get();
            if ($ftpAccounts->isEmpty()) {
                return;
            }

            foreach ($ftpAccounts as $account) {
                $account->delete();
            }

            Log::channel('commands')->info('FTP accounts cleaned up', [
                'domain' => $domain->name,
                'count' => $ftpAccounts->count(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('commands')->warning('Failed to clean up FTP accounts', [
                'domain' => $domain->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Archive domain directory to trash.
     */
    protected function archiveDomainDirectory(Domain $domain, string $username): void
    {
        $domainPath = "/home/{$username}/domains/{$domain->name}";
        $trashPath = "/home/{$username}/.trash/domains/{$domain->name}_" . now()->format('YmdHis');

        $this->executor->executeAsRoot('mkdir', ['-p', dirname($trashPath)]);
        $this->executor->executeAsRoot('mv', [$domainPath, $trashPath]);
    }

    /**
     * Create subdomain directory.
     */
    protected function createSubdomainDirectory(Subdomain $subdomain, Domain $domain): void
    {
        $username = $this->getUsername($domain->user);
        $subdomainPath = "/home/{$username}/domains/{$domain->name}/subdomains/{$subdomain->name}";

        $this->executor->executeAsRoot('mkdir', ['-p', $subdomainPath]);
        $this->executor->executeAsRoot('chown', ['-R', "{$username}:{$username}", $subdomainPath]);
        $this->executor->executeAsRoot('chmod', ['-R', '755', $subdomainPath]);

        // Create default index
        $content = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome to {$subdomain->full_name}</title>
</head>
<body>
    <h1>Welcome to {$subdomain->full_name}</h1>
    <p>Subdomain is ready.</p>
</body>
</html>
HTML;

        $tempFile = "/tmp/index_" . Str::random(8) . ".html";
        file_put_contents($tempFile, $content);

        $this->executor->executeAsRoot('cp', [$tempFile, "{$subdomainPath}/index.html"]);
        $this->executor->executeAsRoot('chown', ["{$username}:{$username}", "{$subdomainPath}/index.html"]);

        unlink($tempFile);

        $subdomain->update(['document_root' => $subdomainPath]);
    }
}
