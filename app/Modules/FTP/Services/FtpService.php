<?php

declare(strict_types=1);

namespace App\Modules\FTP\Services;

use App\Modules\Domain\Models\Domain;
use App\Modules\FTP\Models\FtpAccount;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class FtpService
{
    protected string $ftpServer;
    protected string $configPath;
    protected string $usersDbPath;

    public function __construct()
    {
        $this->ftpServer = config('vsispanel.ftp.server', 'proftpd');
        $this->configPath = config('vsispanel.ftp.config_path', '/etc/proftpd/proftpd.conf');
        $this->usersDbPath = config('vsispanel.ftp.users_db_path', '/etc/proftpd/ftpd.passwd');
    }

    /**
     * Get FTP service status
     */
    public function getStatus(): array
    {
        $serviceName = $this->ftpServer === 'pure-ftpd' ? 'pure-ftpd' : 'proftpd';

        $result = Process::run("sudo systemctl is-active {$serviceName}");
        $isActive = trim($result->output()) === 'active';

        $statusResult = Process::run("sudo systemctl status {$serviceName} --no-pager -l");

        return [
            'running' => $isActive,
            'service' => $serviceName,
            'server_type' => $this->ftpServer,
            'status_output' => $statusResult->output(),
            'config_path' => $this->configPath,
            'users_count' => FtpAccount::active()->count(),
        ];
    }

    /**
     * Get FTP service statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_accounts' => FtpAccount::count(),
            'active_accounts' => FtpAccount::active()->count(),
            'suspended_accounts' => FtpAccount::where('status', FtpAccount::STATUS_SUSPENDED)->count(),
            'disabled_accounts' => FtpAccount::where('status', FtpAccount::STATUS_DISABLED)->count(),
            'total_uploaded' => FtpAccount::sum('total_uploaded_bytes'),
            'total_downloaded' => FtpAccount::sum('total_downloaded_bytes'),
        ];
    }

    /**
     * Create FTP account
     */
    public function createAccount(Domain $domain, array $data): FtpAccount
    {
        // Validate username format
        $this->validateUsername($data['username']);

        // Ensure home directory exists and is valid
        $homeDir = $this->resolveHomeDirectory($domain, $data['home_directory'] ?? null);

        // Create the account
        $account = FtpAccount::create([
            'domain_id' => $domain->id,
            'user_id' => $data['user_id'],
            'username' => $data['username'],
            'password' => $data['password'],
            'home_directory' => $homeDir,
            'status' => $data['status'] ?? FtpAccount::STATUS_ACTIVE,
            'quota_mb' => $data['quota_mb'] ?? null,
            'bandwidth_mb' => $data['bandwidth_mb'] ?? null,
            'upload_bandwidth_kbps' => $data['upload_bandwidth_kbps'] ?? null,
            'download_bandwidth_kbps' => $data['download_bandwidth_kbps'] ?? null,
            'max_connections' => $data['max_connections'] ?? 2,
            'max_connections_per_ip' => $data['max_connections_per_ip'] ?? 2,
            'allowed_ips' => $data['allowed_ips'] ?? null,
            'denied_ips' => $data['denied_ips'] ?? null,
            'allow_upload' => $data['allow_upload'] ?? true,
            'allow_download' => $data['allow_download'] ?? true,
            'allow_mkdir' => $data['allow_mkdir'] ?? true,
            'allow_delete' => $data['allow_delete'] ?? true,
            'allow_rename' => $data['allow_rename'] ?? true,
            'description' => $data['description'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        // Sync to FTP server
        $this->syncAccount($account);

        return $account;
    }

    /**
     * Update FTP account
     */
    public function updateAccount(FtpAccount $account, array $data): FtpAccount
    {
        // If username is being changed, validate it
        if (isset($data['username']) && $data['username'] !== $account->username) {
            $this->validateUsername($data['username']);
        }

        // Update the account
        $account->update($data);

        // Sync to FTP server
        $this->syncAccount($account);

        return $account;
    }

    /**
     * Delete FTP account
     */
    public function deleteAccount(FtpAccount $account): bool
    {
        // Remove from FTP server first
        $this->removeFromServer($account);

        // Soft delete
        return $account->delete();
    }

    /**
     * Change password
     */
    public function changePassword(FtpAccount $account, string $newPassword): bool
    {
        $account->update(['password' => $newPassword]);
        $this->syncAccount($account);

        return true;
    }

    /**
     * Suspend account
     */
    public function suspendAccount(FtpAccount $account): bool
    {
        $account->suspend();
        $this->syncAccount($account);

        return true;
    }

    /**
     * Activate account
     */
    public function activateAccount(FtpAccount $account): bool
    {
        $account->activate();
        $this->syncAccount($account);

        return true;
    }

    /**
     * Validate username format
     */
    public function validateUsername(string $username): bool
    {
        // Username must be 3-32 characters, alphanumeric with underscores
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,31}$/', $username)) {
            throw new RuntimeException(
                'Invalid username. Must start with a letter, contain only letters, numbers, and underscores, and be 3-32 characters long.'
            );
        }

        // Check if username already exists
        if (FtpAccount::where('username', $username)->exists()) {
            throw new RuntimeException('Username already exists.');
        }

        // Check for reserved usernames
        $reserved = ['root', 'admin', 'administrator', 'ftp', 'anonymous', 'www-data', 'nobody'];
        if (in_array(strtolower($username), $reserved)) {
            throw new RuntimeException('This username is reserved and cannot be used.');
        }

        return true;
    }

    /**
     * Resolve home directory path
     */
    protected function resolveHomeDirectory(Domain $domain, ?string $subPath = null): string
    {
        $basePath = config('vsispanel.hosting.web_root', '/var/www');
        $domainPath = "{$basePath}/{$domain->name}";

        if ($subPath) {
            // Sanitize subpath
            $subPath = trim($subPath, '/');
            $subPath = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $subPath);
            $fullPath = "{$domainPath}/{$subPath}";
        } else {
            $fullPath = $domainPath;
        }

        // Ensure path is within domain directory
        $realBase = realpath($domainPath) ?: $domainPath;
        if (!str_starts_with($fullPath, $realBase) && $fullPath !== $domainPath) {
            throw new RuntimeException('Invalid home directory path.');
        }

        return $fullPath;
    }

    /**
     * Sync account to FTP server
     */
    protected function syncAccount(FtpAccount $account): void
    {
        if ($this->ftpServer === 'pure-ftpd') {
            $this->syncPureFtpd($account);
        } else {
            $this->syncProftpd($account);
        }
    }

    /**
     * Sync to ProFTPD
     */
    protected function syncProftpd(FtpAccount $account): void
    {
        // Generate password hash for ProFTPD (using ftpasswd format)
        $passwordHash = $this->generateProftpdPassword($account->password);

        // Read existing users file
        $usersFile = $this->usersDbPath;
        $users = [];

        // Ensure directory exists with sudo
        $usersDir = dirname($usersFile);
        if (!File::isDirectory($usersDir)) {
            Process::run("sudo mkdir -p {$usersDir}");
            Process::run("sudo chmod 755 {$usersDir}");
        }

        // Ensure group file exists
        $groupFile = $usersDir . '/ftpd.group';
        if (!File::exists($groupFile)) {
            $gid = config('vsispanel.ftp.default_gid', 33);
            $groupContent = "ftpgroup:x:{$gid}:";
            Process::run("echo '{$groupContent}' | sudo tee {$groupFile} > /dev/null");
            Process::run("sudo chmod 600 {$groupFile}");
        }

        // Read existing file with sudo if it exists
        if (File::exists($usersFile)) {
            $result = Process::run("sudo cat {$usersFile}");
            $lines = explode("\n", $result->output());
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }
                $parts = explode(':', $line);
                if (count($parts) >= 7 && $parts[0] !== $account->username) {
                    $users[$parts[0]] = $line;
                }
            }
        }

        // Add/update current account if active
        if ($account->isActive()) {
            $uid = config('vsispanel.ftp.default_uid', 33);
            $gid = config('vsispanel.ftp.default_gid', 33);

            $users[$account->username] = implode(':', [
                $account->username,
                $passwordHash,
                $uid,
                $gid,
                $account->description ?? "FTP account",
                $account->home_directory,
                '/bin/false',
            ]);
        }

        // Write users file using sudo
        $content = "# ProFTPD virtual users file\n";
        $content .= "# Generated by VSISPanel at " . now()->toIso8601String() . "\n\n";
        $content .= implode("\n", array_values($users)) . "\n";

        // Write to temp file first, then move with sudo
        $tempFile = sys_get_temp_dir() . '/ftpd_passwd_' . uniqid();
        File::put($tempFile, $content);
        Process::run("sudo mv {$tempFile} {$usersFile}");
        Process::run("sudo chmod 600 {$usersFile}");
        Process::run("sudo chown root:root {$usersFile}");

        // Generate account-specific config
        $this->generateProftpdConfig();
    }

    /**
     * Generate ProFTPD main config include
     */
    protected function generateProftpdConfig(): void
    {
        $includeDir = dirname($this->configPath) . '/conf.d';
        $vsispanelConf = "{$includeDir}/vsispanel.conf";

        // Ensure include directory exists with sudo
        if (!File::isDirectory($includeDir)) {
            Process::run("sudo mkdir -p {$includeDir}");
            Process::run("sudo chmod 755 {$includeDir}");
        }

        $accounts = FtpAccount::active()->with('domain')->get();

        $config = [];
        $config[] = "# VSISPanel FTP Configuration";
        $config[] = "# Generated at " . now()->toIso8601String();
        $config[] = "";
        $config[] = "# Virtual users authentication";
        $config[] = "AuthUserFile {$this->usersDbPath}";
        $config[] = "AuthGroupFile /etc/proftpd/ftpd.group";
        $config[] = "AuthOrder mod_auth_file.c";
        $config[] = "";
        $config[] = "# Require valid user";
        $config[] = "RequireValidShell off";
        $config[] = "";

        foreach ($accounts as $account) {
            $config[] = $account->toProftpdConfig();
            $config[] = "";
        }

        // Write to temp file first, then move with sudo
        $tempFile = sys_get_temp_dir() . '/proftpd_vsispanel_' . uniqid() . '.conf';
        File::put($tempFile, implode("\n", $config));
        Process::run("sudo mv {$tempFile} {$vsispanelConf}");
        Process::run("sudo chmod 644 {$vsispanelConf}");
    }

    /**
     * Sync to Pure-FTPd
     */
    protected function syncPureFtpd(FtpAccount $account): void
    {
        $pureDbPath = config('vsispanel.ftp.pureftpd_db', '/etc/pure-ftpd/pureftpd.pdb');
        $passwdPath = config('vsispanel.ftp.pureftpd_passwd', '/etc/pure-ftpd/pureftpd.passwd');

        // Ensure directory exists with sudo
        $passwdDir = dirname($passwdPath);
        if (!File::isDirectory($passwdDir)) {
            Process::run("sudo mkdir -p {$passwdDir}");
            Process::run("sudo chmod 755 {$passwdDir}");
        }

        if ($account->isActive()) {
            // Create/update virtual user
            $userData = $account->toPureFtpdUser();

            // Use pure-pw to manage users
            $password = $account->getOriginal('password'); // Get unhashed password if available

            // Update passwd file entry
            $this->updatePureFtpdPasswd($account, $passwdPath);

            // Rebuild database with sudo
            Process::run("sudo pure-pw mkdb {$pureDbPath} -f {$passwdPath}");
        } else {
            // Remove user with sudo
            Process::run("sudo pure-pw userdel {$account->username} -f {$passwdPath} -m");
        }
    }

    /**
     * Update Pure-FTPd passwd file
     */
    protected function updatePureFtpdPasswd(FtpAccount $account, string $passwdPath): void
    {
        // Ensure directory exists with sudo
        $passwdDir = dirname($passwdPath);
        if (!File::isDirectory($passwdDir)) {
            Process::run("sudo mkdir -p {$passwdDir}");
            Process::run("sudo chmod 755 {$passwdDir}");
        }

        $users = [];

        if (File::exists($passwdPath)) {
            $result = Process::run("sudo cat {$passwdPath}");
            $lines = explode("\n", $result->output());
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                $parts = explode(':', $line);
                if (count($parts) >= 2 && $parts[0] !== $account->username) {
                    $users[$parts[0]] = $line;
                }
            }
        }

        // Add current account
        $uid = config('vsispanel.ftp.default_uid', 33);
        $gid = config('vsispanel.ftp.default_gid', 33);

        // Pure-FTPd passwd format: user:password:uid:gid:gecos:home:shell
        $users[$account->username] = implode(':', [
            $account->username,
            $account->password, // Already hashed
            $uid,
            $gid,
            $account->description ?? '',
            $account->home_directory,
            '/bin/false',
        ]);

        $content = implode("\n", array_values($users)) . "\n";

        // Write to temp file first, then move with sudo
        $tempFile = sys_get_temp_dir() . '/pureftpd_passwd_' . uniqid();
        File::put($tempFile, $content);
        Process::run("sudo mv {$tempFile} {$passwdPath}");
        Process::run("sudo chmod 600 {$passwdPath}");
        Process::run("sudo chown root:root {$passwdPath}");
    }

    /**
     * Remove account from FTP server
     */
    protected function removeFromServer(FtpAccount $account): void
    {
        if ($this->ftpServer === 'pure-ftpd') {
            $passwdPath = config('vsispanel.ftp.pureftpd_passwd', '/etc/pure-ftpd/pureftpd.passwd');
            $pureDbPath = config('vsispanel.ftp.pureftpd_db', '/etc/pure-ftpd/pureftpd.pdb');

            Process::run("sudo pure-pw userdel {$account->username} -f {$passwdPath}");
            Process::run("sudo pure-pw mkdb {$pureDbPath} -f {$passwdPath}");
        } else {
            // Remove from ProFTPD users file
            $this->syncProftpd($account);
        }
    }

    /**
     * Generate ProFTPD password hash
     */
    protected function generateProftpdPassword(string $hashedPassword): string
    {
        // ProFTPD can use various hash formats
        // We'll use the bcrypt hash directly since modern ProFTPD supports it
        return $hashedPassword;
    }

    /**
     * Get connected users (currently active sessions)
     */
    public function getConnectedUsers(): array
    {
        if ($this->ftpServer === 'pure-ftpd') {
            $result = Process::run('sudo pure-ftpwho -s');
        } else {
            $result = Process::run('sudo ftpwho -v');
        }

        $users = [];
        $lines = explode("\n", $result->output());

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, 'Service') || str_starts_with($line, '+')) {
                continue;
            }

            // Parse the output based on server type
            // Format varies by FTP server
            $users[] = $line;
        }

        return $users;
    }

    /**
     * Disconnect a user session
     */
    public function disconnectUser(string $username): bool
    {
        if ($this->ftpServer === 'pure-ftpd') {
            $result = Process::run("sudo pure-ftpwho -k {$username}");
        } else {
            // ProFTPD uses ftpshut or kill
            $result = Process::run("sudo pkill -u {$username} -f proftpd");
        }

        return $result->successful();
    }

    /**
     * Restart FTP service
     */
    public function restart(): bool
    {
        $serviceName = $this->ftpServer === 'pure-ftpd' ? 'pure-ftpd' : 'proftpd';
        $result = Process::run("sudo systemctl restart {$serviceName}");

        return $result->successful();
    }

    /**
     * Reload FTP service configuration
     */
    public function reload(): bool
    {
        $serviceName = $this->ftpServer === 'pure-ftpd' ? 'pure-ftpd' : 'proftpd';
        $result = Process::run("sudo systemctl reload {$serviceName}");

        return $result->successful();
    }

    /**
     * Test FTP configuration
     */
    public function testConfig(): array
    {
        if ($this->ftpServer === 'proftpd') {
            $result = Process::run("sudo proftpd -t -c {$this->configPath}");

            return [
                'valid' => $result->successful(),
                'output' => $result->output() . $result->errorOutput(),
            ];
        }

        // Pure-FTPd doesn't have a config test command
        return [
            'valid' => true,
            'output' => 'Pure-FTPd configuration test not available',
        ];
    }

    /**
     * Get FTP logs
     */
    public function getLogs(int $lines = 100): array
    {
        $logPath = $this->ftpServer === 'pure-ftpd'
            ? '/var/log/pure-ftpd/transfer.log'
            : '/var/log/proftpd/proftpd.log';

        if (!File::exists($logPath)) {
            return [];
        }

        $result = Process::run("sudo tail -n {$lines} {$logPath}");
        $logLines = explode("\n", trim($result->output()));

        return array_filter($logLines);
    }

    /**
     * Get transfer logs
     */
    public function getTransferLogs(int $lines = 100): array
    {
        $logPath = $this->ftpServer === 'pure-ftpd'
            ? '/var/log/pure-ftpd/transfer.log'
            : '/var/log/proftpd/xferlog';

        if (!File::exists($logPath)) {
            return [];
        }

        $result = Process::run("sudo tail -n {$lines} {$logPath}");
        $logLines = explode("\n", trim($result->output()));

        return array_filter($logLines);
    }

    /**
     * Calculate directory size
     */
    public function getDirectorySize(string $path): int
    {
        if (!File::isDirectory($path)) {
            return 0;
        }

        $result = Process::run("sudo du -sb {$path} | cut -f1");
        return (int) trim($result->output());
    }
}
