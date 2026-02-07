<?php

declare(strict_types=1);

namespace App\Modules\Mail\Services;

use App\Services\CommandResult;
use App\Services\SystemCommandExecutor;
use Illuminate\Support\Facades\File;
use RuntimeException;

class PostfixService
{
    protected string $virtualDomainsFile;
    protected string $virtualMailboxesFile;
    protected string $virtualUsersFile;
    protected string $virtualAliasesFile;
    protected string $mailBaseDir;

    public function __construct(
        protected SystemCommandExecutor $executor
    ) {
        $this->virtualDomainsFile = config('vsispanel.mail.virtual_domains_file', '/etc/postfix/virtual_domains');
        $this->virtualMailboxesFile = config('vsispanel.mail.virtual_mailboxes_file', '/etc/postfix/virtual_mailboxes');
        $this->virtualUsersFile = config('vsispanel.mail.virtual_users_file', '/etc/postfix/virtual_users');
        $this->virtualAliasesFile = config('vsispanel.mail.virtual_aliases_file', '/etc/postfix/virtual_aliases');
        $this->mailBaseDir = config('vsispanel.mail.mail_base_dir', '/var/mail/vhosts');
    }

    /**
     * Add a domain to the virtual domains list.
     */
    public function addDomain(string $domain): void
    {
        $this->ensureFileExists($this->virtualDomainsFile);

        // Check if domain already exists
        $content = $this->readFileAsRoot($this->virtualDomainsFile);
        if (preg_match('/^' . preg_quote($domain, '/') . '\s/m', $content)) {
            return; // Domain already exists
        }

        // Add domain
        $this->appendToFileAsRoot($this->virtualDomainsFile, "{$domain}\tOK\n");

        // Create maildir base for domain
        $domainMailDir = "{$this->mailBaseDir}/{$domain}";
        if (!File::isDirectory($domainMailDir)) {
            $this->executor->executeAsRoot('mkdir', ['-p', $domainMailDir]);
            $this->executor->executeAsRoot('chown', ['-R', 'vmail:vmail', $domainMailDir]);
            $this->executor->executeAsRoot('chmod', ['750', $domainMailDir]);
        }

        // Reload Postfix
        $this->postmap($this->virtualDomainsFile);
        $this->reload();
    }

    /**
     * Remove a domain from the virtual domains list.
     */
    public function removeDomain(string $domain): void
    {
        if (!File::exists($this->virtualDomainsFile)) {
            return;
        }

        $content = $this->readFileAsRoot($this->virtualDomainsFile);
        $lines = explode("\n", $content);
        $filtered = array_filter($lines, function ($line) use ($domain) {
            return !preg_match('/^' . preg_quote($domain, '/') . '\s/', $line);
        });

        $this->writeFileAsRoot($this->virtualDomainsFile, implode("\n", $filtered));
        $this->postmap($this->virtualDomainsFile);
        $this->reload();
    }

    /**
     * Add a mailbox for an email address.
     */
    public function addMailbox(string $email, string $password): void
    {
        [$user, $domain] = explode('@', $email);

        // Add to virtual mailboxes
        $this->ensureFileExists($this->virtualMailboxesFile);
        $mailboxPath = "{$domain}/{$user}/";
        $this->appendToFileAsRoot($this->virtualMailboxesFile, "{$email}\t{$mailboxPath}\n");
        $this->postmap($this->virtualMailboxesFile);

        // Hash password and add to virtual users
        $hashedPassword = $this->hashPassword($password);
        $this->ensureFileExists($this->virtualUsersFile);
        $this->appendToFileAsRoot($this->virtualUsersFile, "{$email}:{$hashedPassword}\n");

        // Create maildir
        $maildirPath = "{$this->mailBaseDir}/{$domain}/{$user}";
        $this->executor->executeAsRoot('mkdir', ['-p', "{$maildirPath}/Maildir/{cur,new,tmp}"]);
        $this->executor->executeAsRoot('chown', ['-R', 'vmail:vmail', $maildirPath]);
        $this->executor->executeAsRoot('chmod', ['-R', '700', $maildirPath]);

        $this->reload();
    }

    /**
     * Remove a mailbox.
     */
    public function removeMailbox(string $email): void
    {
        [$user, $domain] = explode('@', $email);

        // Remove from virtual mailboxes
        $this->removeLineFromFile($this->virtualMailboxesFile, $email);
        $this->postmap($this->virtualMailboxesFile);

        // Remove from virtual users
        $this->removeLineFromFile($this->virtualUsersFile, $email);

        // Remove maildir
        $maildirPath = "{$this->mailBaseDir}/{$domain}/{$user}";
        if (File::isDirectory($maildirPath)) {
            $this->executor->executeAsRoot('rm', ['-rf', $maildirPath]);
        }

        $this->reload();
    }

    /**
     * Add an email alias.
     */
    public function addAlias(string $source, string $destination): void
    {
        $this->ensureFileExists($this->virtualAliasesFile);

        // Check if alias already exists
        $content = $this->readFileAsRoot($this->virtualAliasesFile);
        if (preg_match('/^' . preg_quote($source, '/') . '\s/m', $content)) {
            // Update existing alias
            $this->removeLineFromFile($this->virtualAliasesFile, $source);
        }

        $this->appendToFileAsRoot($this->virtualAliasesFile, "{$source}\t{$destination}\n");
        $this->postmap($this->virtualAliasesFile);
        $this->reload();
    }

    /**
     * Remove an email alias.
     */
    public function removeAlias(string $source): void
    {
        $this->removeLineFromFile($this->virtualAliasesFile, $source);
        $this->postmap($this->virtualAliasesFile);
        $this->reload();
    }

    /**
     * Add email forwarding.
     */
    public function addForwarding(string $email, string $forwardTo, bool $keepCopy = true): void
    {
        $destination = $keepCopy ? "{$email},{$forwardTo}" : $forwardTo;
        $this->addAlias($email, $destination);
    }

    /**
     * Set mailbox quota (via Dovecot userdb).
     */
    public function setQuota(string $email, int $quotaMB): void
    {
        // Quota is actually managed by Dovecot, but we can store it in postfix for reference
        // The actual quota enforcement happens in DovecotService
    }

    /**
     * Reload Postfix configuration.
     */
    public function reload(): CommandResult
    {
        return $this->executor->executeAsRoot('systemctl', ['reload', 'postfix']);
    }

    /**
     * Restart Postfix service.
     */
    public function restart(): CommandResult
    {
        return $this->executor->executeAsRoot('systemctl', ['restart', 'postfix']);
    }

    /**
     * Get mail queue status.
     */
    public function getQueueStatus(): array
    {
        $result = $this->executor->executeAsRoot('postqueue', ['-p']);

        if (!$result->success) {
            return [
                'count' => 0,
                'messages' => [],
                'error' => $result->stderr,
            ];
        }

        return $this->parseMailQueue($result->stdout);
    }

    /**
     * Flush the mail queue.
     */
    public function flushQueue(): CommandResult
    {
        return $this->executor->executeAsRoot('postqueue', ['-f']);
    }

    /**
     * Delete a specific message from queue.
     */
    public function deleteFromQueue(string $queueId): CommandResult
    {
        return $this->executor->executeAsRoot('postsuper', ['-d', $queueId]);
    }

    /**
     * Delete all messages from queue.
     */
    public function clearQueue(): CommandResult
    {
        return $this->executor->executeAsRoot('postsuper', ['-d', 'ALL']);
    }

    /**
     * Get Postfix status.
     */
    public function getStatus(): array
    {
        $statusResult = $this->executor->executeAsRoot('systemctl', ['is-active', 'postfix']);
        $isRunning = $statusResult->success && trim($statusResult->stdout) === 'active';

        $configResult = $this->executor->execute('postconf', ['mail_version']);
        $version = 'Unknown';
        if ($configResult->success && preg_match('/mail_version\s*=\s*(.+)/', $configResult->stdout, $matches)) {
            $version = trim($matches[1]);
        }

        return [
            'running' => $isRunning,
            'version' => $version,
            'queue' => $this->getQueueStatus(),
        ];
    }

    /**
     * Hash a password using doveadm (SSHA512).
     */
    public function hashPassword(string $password): string
    {
        $result = $this->executor->execute('doveadm', ['pw', '-s', 'SSHA512', '-p', $password]);

        if (!$result->success) {
            throw new RuntimeException("Failed to hash password: " . $result->stderr);
        }

        return trim($result->stdout);
    }

    /**
     * Run postmap on a file.
     */
    protected function postmap(string $file): CommandResult
    {
        return $this->executor->executeAsRoot('postmap', [$file]);
    }

    /**
     * Ensure a file exists with correct permissions.
     */
    protected function ensureFileExists(string $file): void
    {
        if (!File::exists($file)) {
            // Use touch via executeAsRoot since /etc/postfix requires root
            $dir = dirname($file);
            if (!File::isDirectory($dir)) {
                $this->executor->executeAsRoot('mkdir', ['-p', $dir]);
            }
            $this->executor->executeAsRoot('touch', [$file]);
            $this->executor->executeAsRoot('chown', ['root:root', $file]);
            $this->executor->executeAsRoot('chmod', ['644', $file]);
        }
    }

    /**
     * Read a file as root.
     */
    protected function readFileAsRoot(string $file): string
    {
        $result = $this->executor->executeAsRoot('cat', [$file]);
        return $result->success ? $result->stdout : '';
    }

    /**
     * Write content to a file as root.
     */
    protected function writeFileAsRoot(string $file, string $content): void
    {
        // Write to a temp file first, then move it
        $tempFile = sys_get_temp_dir() . '/postfix_' . uniqid();
        File::put($tempFile, $content);
        $this->executor->executeAsRoot('cp', [$tempFile, $file]);
        $this->executor->executeAsRoot('chown', ['root:root', $file]);
        $this->executor->executeAsRoot('chmod', ['644', $file]);
        @unlink($tempFile);
    }

    /**
     * Append content to a file as root.
     */
    protected function appendToFileAsRoot(string $file, string $content): void
    {
        // Use tee -a to append as root
        $this->executor->executeAsRoot('bash', ['-c', "echo " . escapeshellarg($content) . " | tee -a " . escapeshellarg($file) . " > /dev/null"]);
    }

    /**
     * Remove a line from a file that starts with a pattern.
     */
    protected function removeLineFromFile(string $file, string $pattern): void
    {
        if (!File::exists($file)) {
            return;
        }

        $content = $this->readFileAsRoot($file);
        $lines = explode("\n", $content);
        $filtered = array_filter($lines, function ($line) use ($pattern) {
            return !str_starts_with(trim($line), $pattern);
        });

        $this->writeFileAsRoot($file, implode("\n", $filtered));
    }

    /**
     * Parse mail queue output.
     */
    protected function parseMailQueue(string $output): array
    {
        $messages = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            // Queue ID format: queue_id sender_size date_time queue_name
            if (preg_match('/^([A-F0-9]+)\s+(\d+)\s+(.+?)\s+(.+)$/', $line, $matches)) {
                $messages[] = [
                    'queue_id' => $matches[1],
                    'size' => (int) $matches[2],
                    'date' => $matches[3],
                    'sender' => $matches[4],
                ];
            }
        }

        // Extract total count from last line
        $count = count($messages);
        if (preg_match('/-- (\d+) Kbytes in (\d+) Request/', $output, $matches)) {
            $count = (int) $matches[2];
        }

        return [
            'count' => $count,
            'messages' => $messages,
        ];
    }

    /**
     * Generate main.cf configuration.
     */
    public function generateMainConfig(array $options = []): string
    {
        $hostname = $options['hostname'] ?? gethostname();
        $domain = $options['domain'] ?? $hostname;

        return view('templates.postfix.main-cf', [
            'hostname' => $hostname,
            'domain' => $domain,
            'virtualDomainsFile' => $this->virtualDomainsFile,
            'virtualMailboxesFile' => $this->virtualMailboxesFile,
            'virtualAliasesFile' => $this->virtualAliasesFile,
            'mailBaseDir' => $this->mailBaseDir,
        ])->render();
    }

    /**
     * Apply main.cf configuration.
     */
    public function applyMainConfig(array $options = []): void
    {
        $configPath = '/etc/postfix/main.cf';
        $backupPath = "{$configPath}.backup." . date('YmdHis');

        // Backup existing config
        if (File::exists($configPath)) {
            $this->executor->executeAsRoot('cp', [$configPath, $backupPath]);
        }

        // Generate and write new config
        $config = $this->generateMainConfig($options);
        $this->writeFileAsRoot($configPath, $config);

        // Reload postfix
        $this->reload();
    }
}
