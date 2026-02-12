<?php

declare(strict_types=1);

namespace App\Modules\Mail\Services;

use App\Services\CommandResult;
use App\Services\SystemCommandExecutor;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DovecotService
{
    protected string $userdbFile;
    protected string $passwdFile;
    protected string $mailBaseDir;
    protected int $vmailUid;
    protected int $vmailGid;

    public function __construct(
        protected SystemCommandExecutor $executor
    ) {
        $this->userdbFile = config('vsispanel.mail.dovecot_userdb_file', '/etc/dovecot/users');
        $this->passwdFile = config('vsispanel.mail.dovecot_passwd_file', '/etc/dovecot/passwd');
        $this->mailBaseDir = config('vsispanel.mail.mail_base_dir', '/var/mail/vhosts');
        $this->vmailUid = config('vsispanel.mail.vmail_uid', 5000);
        $this->vmailGid = config('vsispanel.mail.vmail_gid', 5000);
    }

    /**
     * Create a mailbox with quota.
     */
    public function createMailbox(string $email, string $password, int $quotaMB = 0): void
    {
        [$user, $domain] = explode('@', $email);

        // Hash password
        $hashedPassword = $this->hashPassword($password);

        // Calculate mailbox path
        $mailboxPath = "{$this->mailBaseDir}/{$domain}/{$user}";

        // Build user entry with quota
        // Format: user:password:uid:gid::home:extra_fields
        $extraFields = "userdb_mail=maildir:{$mailboxPath}/Maildir";
        if ($quotaMB > 0) {
            $quotaBytes = $quotaMB * 1024 * 1024;
            $extraFields .= " userdb_quota_rule=*:bytes={$quotaBytes}";
        }

        $userEntry = "{$email}:{$hashedPassword}:{$this->vmailUid}:{$this->vmailGid}::{$mailboxPath}:{$extraFields}";

        // Add to passwd file
        $this->ensureFileExists($this->passwdFile);
        $this->removeUserFromFile($this->passwdFile, $email);
        File::append($this->passwdFile, $userEntry . "\n");

        // Create maildir structure
        $this->createMaildir($email);

        $this->reload();
    }

    /**
     * Delete a mailbox.
     */
    public function deleteMailbox(string $email): void
    {
        [$user, $domain] = explode('@', $email);

        // Remove from passwd file
        $this->removeUserFromFile($this->passwdFile, $email);

        // Remove maildir
        $mailboxPath = "{$this->mailBaseDir}/{$domain}/{$user}";
        if (File::isDirectory($mailboxPath)) {
            $this->executor->executeAsRoot('rm', ['-rf', $mailboxPath]);
        }

        $this->reload();
    }

    /**
     * Change mailbox password.
     */
    public function changePassword(string $email, string $newPassword): void
    {
        $hashedPassword = $this->hashPassword($newPassword);

        if (!File::exists($this->passwdFile)) {
            throw new RuntimeException("Password file not found.");
        }

        $content = File::get($this->passwdFile);
        $lines = explode("\n", $content);
        $updated = false;

        foreach ($lines as &$line) {
            if (str_starts_with($line, "{$email}:")) {
                $parts = explode(':', $line);
                $parts[1] = $hashedPassword;
                $line = implode(':', $parts);
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            throw new RuntimeException("Mailbox not found: {$email}");
        }

        File::put($this->passwdFile, implode("\n", $lines));
        $this->reload();
    }

    /**
     * Set mailbox quota.
     */
    public function setQuota(string $email, int $quotaMB): void
    {
        if (!File::exists($this->passwdFile)) {
            throw new RuntimeException("Password file not found.");
        }

        $content = File::get($this->passwdFile);
        $lines = explode("\n", $content);
        $updated = false;

        foreach ($lines as &$line) {
            if (str_starts_with($line, "{$email}:")) {
                // Remove existing quota rule and add new one
                $line = preg_replace('/\s*userdb_quota_rule=[^\s]*/', '', $line);

                if ($quotaMB > 0) {
                    $quotaBytes = $quotaMB * 1024 * 1024;
                    $line .= " userdb_quota_rule=*:bytes={$quotaBytes}";
                }

                $updated = true;
                break;
            }
        }

        if (!$updated) {
            throw new RuntimeException("Mailbox not found: {$email}");
        }

        File::put($this->passwdFile, implode("\n", $lines));
        $this->reload();
    }

    /**
     * Get mailbox size in bytes.
     */
    public function getMailboxSize(string $email): int
    {
        $result = $this->executor->executeAsRoot('doveadm', ['quota', 'get', '-u', $email]);

        if (!$result->success) {
            // Fallback to du command
            [$user, $domain] = explode('@', $email);
            $mailboxPath = "{$this->mailBaseDir}/{$domain}/{$user}";

            $duResult = $this->executor->executeAsRoot('du', ['-sb', $mailboxPath]);
            if ($duResult->success && preg_match('/^(\d+)/', $duResult->stdout, $matches)) {
                return (int) $matches[1];
            }

            return 0;
        }

        // Parse doveadm output
        // Format: User QUOTA Type Value Limit %
        if (preg_match('/STORAGE\s+(\d+)/', $result->stdout, $matches)) {
            return (int) $matches[1] * 1024; // Convert KB to bytes
        }

        return 0;
    }

    /**
     * Get mailbox information.
     */
    public function getMailboxInfo(string $email): array
    {
        [$user, $domain] = explode('@', $email);
        $mailboxPath = "{$this->mailBaseDir}/{$domain}/{$user}/Maildir";

        // Count messages
        $messageCount = 0;
        $newCount = 0;
        $unreadCount = 0;

        if (File::isDirectory("{$mailboxPath}/new")) {
            $newCount = count(File::files("{$mailboxPath}/new"));
        }
        if (File::isDirectory("{$mailboxPath}/cur")) {
            $curFiles = File::files("{$mailboxPath}/cur");
            $messageCount = count($curFiles) + $newCount;
            // Count unread (files without 'S' seen flag)
            foreach ($curFiles as $file) {
                if (!str_contains($file->getFilename(), ':2,') || !str_contains($file->getFilename(), 'S')) {
                    $unreadCount++;
                }
            }
        }

        // Get last login
        $lastLogin = null;
        $result = $this->executor->executeAsRoot('doveadm', ['user', '-u', $email, '-f', 'last_login']);
        if ($result->success && preg_match('/last_login=(\d+)/', $result->stdout, $matches)) {
            $lastLogin = date('Y-m-d H:i:s', (int) $matches[1]);
        }

        // Get quota info
        $quotaUsed = $this->getMailboxSize($email);
        $quotaLimit = 0;

        if (File::exists($this->passwdFile)) {
            $content = File::get($this->passwdFile);
            if (preg_match('/^' . preg_quote($email, '/') . ':.*userdb_quota_rule=\*:bytes=(\d+)/m', $content, $matches)) {
                $quotaLimit = (int) $matches[1];
            }
        }

        return [
            'email' => $email,
            'message_count' => $messageCount,
            'new_count' => $newCount,
            'unread_count' => $unreadCount,
            'last_login' => $lastLogin,
            'quota_used' => $quotaUsed,
            'quota_limit' => $quotaLimit,
            'quota_percent' => $quotaLimit > 0 ? round(($quotaUsed / $quotaLimit) * 100, 2) : 0,
        ];
    }

    /**
     * Reload Dovecot configuration.
     */
    public function reload(): CommandResult
    {
        return $this->executor->executeAsRoot('systemctl', ['reload', 'dovecot']);
    }

    /**
     * Restart Dovecot service.
     */
    public function restart(): CommandResult
    {
        return $this->executor->executeAsRoot('systemctl', ['restart', 'dovecot']);
    }

    /**
     * Get Dovecot status.
     */
    public function getStatus(): array
    {
        $statusResult = $this->executor->executeAsRoot('systemctl', ['is-active', 'dovecot']);
        $isRunning = $statusResult->success && trim($statusResult->stdout) === 'active';

        $versionResult = $this->executor->execute('dovecot', ['--version']);
        $version = 'Unknown';
        if ($versionResult->success) {
            $version = trim(explode("\n", $versionResult->stdout)[0]);
        }

        // Get connected users count
        $connectedUsers = 0;
        $whoResult = $this->executor->executeAsRoot('doveadm', ['who']);
        if ($whoResult->success) {
            $lines = array_filter(explode("\n", $whoResult->stdout));
            $connectedUsers = count($lines) > 0 ? count($lines) - 1 : 0; // Subtract header line
        }

        return [
            'running' => $isRunning,
            'version' => $version,
            'connected_users' => $connectedUsers,
        ];
    }

    /**
     * Get list of connected users.
     */
    public function getConnectedUsers(): array
    {
        $result = $this->executor->executeAsRoot('doveadm', ['who']);

        if (!$result->success) {
            return [];
        }

        $users = [];
        $lines = explode("\n", $result->stdout);

        // Skip header line
        array_shift($lines);

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Format: username #proto (ip) (session time)
            if (preg_match('/^(\S+)\s+\d+\s+(\S+)\s+\(([^)]+)\)\s*\(([^)]+)\)?/', $line, $matches)) {
                $users[] = [
                    'username' => $matches[1],
                    'protocol' => $matches[2],
                    'ip' => $matches[3],
                    'session_time' => $matches[4] ?? null,
                ];
            }
        }

        return $users;
    }

    /**
     * Force disconnect a user.
     */
    public function kickUser(string $email): CommandResult
    {
        return $this->executor->executeAsRoot('doveadm', ['kick', $email]);
    }

    /**
     * Hash a password using doveadm.
     */
    public function hashPassword(string $password): string
    {
        $result = $this->executor->executeAsRoot('/usr/bin/doveadm', ['pw', '-s', 'SSHA512', '-p', $password]);

        if (!$result->success) {
            throw new RuntimeException("Failed to hash password: " . $result->stderr);
        }

        return trim($result->stdout);
    }

    /**
     * Create maildir structure for a user.
     */
    protected function createMaildir(string $email): void
    {
        [$user, $domain] = explode('@', $email);
        $mailboxPath = "{$this->mailBaseDir}/{$domain}/{$user}/Maildir";

        // Create standard maildir directories
        $dirs = [
            $mailboxPath,
            "{$mailboxPath}/cur",
            "{$mailboxPath}/new",
            "{$mailboxPath}/tmp",
            "{$mailboxPath}/.Sent/cur",
            "{$mailboxPath}/.Sent/new",
            "{$mailboxPath}/.Sent/tmp",
            "{$mailboxPath}/.Drafts/cur",
            "{$mailboxPath}/.Drafts/new",
            "{$mailboxPath}/.Drafts/tmp",
            "{$mailboxPath}/.Trash/cur",
            "{$mailboxPath}/.Trash/new",
            "{$mailboxPath}/.Trash/tmp",
            "{$mailboxPath}/.Junk/cur",
            "{$mailboxPath}/.Junk/new",
            "{$mailboxPath}/.Junk/tmp",
        ];

        foreach ($dirs as $dir) {
            $this->executor->executeAsRoot('mkdir', ['-p', $dir]);
        }

        // Set ownership
        $this->executor->executeAsRoot('chown', [
            '-R',
            "{$this->vmailUid}:{$this->vmailGid}",
            "{$this->mailBaseDir}/{$domain}/{$user}",
        ]);

        // Set permissions
        $this->executor->executeAsRoot('chmod', [
            '-R',
            '700',
            "{$this->mailBaseDir}/{$domain}/{$user}",
        ]);
    }

    /**
     * Ensure a file exists with correct permissions.
     */
    protected function ensureFileExists(string $file): void
    {
        if (!File::exists($file)) {
            File::put($file, '');
            $this->executor->executeAsRoot('chown', ['root:dovecot', $file]);
            $this->executor->executeAsRoot('chmod', ['640', $file]);
        }
    }

    /**
     * Remove a user entry from a file.
     */
    protected function removeUserFromFile(string $file, string $email): void
    {
        if (!File::exists($file)) {
            return;
        }

        $content = File::get($file);
        $lines = explode("\n", $content);
        $filtered = array_filter($lines, function ($line) use ($email) {
            return !str_starts_with(trim($line), "{$email}:");
        });

        File::put($file, implode("\n", $filtered));
    }

    /**
     * Generate dovecot.conf configuration.
     */
    public function generateConfig(array $options = []): string
    {
        return view('templates.dovecot.dovecot-conf', [
            'mailBaseDir' => $this->mailBaseDir,
            'passwdFile' => $this->passwdFile,
            'vmailUid' => $this->vmailUid,
            'vmailGid' => $this->vmailGid,
            'protocols' => $options['protocols'] ?? ['imap', 'pop3', 'lmtp'],
            'sslCert' => $options['ssl_cert'] ?? '/etc/ssl/certs/dovecot.pem',
            'sslKey' => $options['ssl_key'] ?? '/etc/ssl/private/dovecot.pem',
        ])->render();
    }
}
