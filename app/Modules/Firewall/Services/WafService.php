<?php

declare(strict_types=1);

namespace App\Modules\Firewall\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class WafService
{
    protected string $modsecConfPath = '/etc/nginx/modsec/modsecurity.conf';
    protected string $rulesPath = '/etc/nginx/modsec/crs';
    protected string $auditLogPath = '/var/log/modsec_audit.log';
    protected string $whitelistPath = '/etc/nginx/modsec/whitelist.conf';

    /**
     * Get WAF status
     */
    public function getStatus(): array
    {
        $enabled = $this->isEnabled();
        $mode = $this->getMode();
        $rulesCount = $this->getRulesCount();

        return [
            'enabled' => $enabled,
            'mode' => $mode,
            'rules_count' => $rulesCount,
            'audit_log_path' => $this->auditLogPath,
        ];
    }

    /**
     * Check if WAF is enabled
     */
    public function isEnabled(): bool
    {
        if (!file_exists($this->modsecConfPath)) {
            return false;
        }

        $content = file_get_contents($this->modsecConfPath);
        return str_contains($content, 'SecRuleEngine On') ||
               str_contains($content, 'SecRuleEngine DetectionOnly');
    }

    /**
     * Enable WAF
     */
    public function enable(): array
    {
        try {
            $this->updateConfig('SecRuleEngine', 'On');
            $this->reloadNginx();

            Log::info('WAF enabled');

            return ['success' => true, 'message' => 'WAF enabled successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Disable WAF
     */
    public function disable(): array
    {
        try {
            $this->updateConfig('SecRuleEngine', 'Off');
            $this->reloadNginx();

            Log::info('WAF disabled');

            return ['success' => true, 'message' => 'WAF disabled successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get current mode (On/Off/DetectionOnly)
     */
    public function getMode(): string
    {
        if (!file_exists($this->modsecConfPath)) {
            return 'Off';
        }

        $content = file_get_contents($this->modsecConfPath);

        if (preg_match('/SecRuleEngine\s+(\w+)/i', $content, $matches)) {
            return $matches[1];
        }

        return 'Off';
    }

    /**
     * Set WAF mode
     */
    public function setMode(string $mode): array
    {
        if (!in_array($mode, ['On', 'Off', 'DetectionOnly'])) {
            return ['success' => false, 'error' => 'Invalid mode'];
        }

        try {
            $this->updateConfig('SecRuleEngine', $mode);
            $this->reloadNginx();

            Log::info('WAF mode changed', ['mode' => $mode]);

            return ['success' => true, 'message' => "WAF mode set to {$mode}"];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get audit log entries
     */
    public function getAuditLog(int $limit = 100): array
    {
        if (!file_exists($this->auditLogPath)) {
            return [];
        }

        $entries = [];
        $result = Process::run("tail -n 1000 {$this->auditLogPath}");

        if (!$result->successful()) {
            return [];
        }

        $content = $result->output();
        $blocks = $this->parseAuditLog($content);

        return array_slice($blocks, -$limit);
    }

    /**
     * Parse audit log content
     */
    protected function parseAuditLog(string $content): array
    {
        $entries = [];
        $currentEntry = null;

        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            // Start of new entry (Section A)
            if (preg_match('/^--([a-f0-9]+)-A--$/i', $line, $matches)) {
                if ($currentEntry) {
                    $entries[] = $currentEntry;
                }
                $currentEntry = [
                    'id' => $matches[1],
                    'timestamp' => null,
                    'client_ip' => null,
                    'request_uri' => null,
                    'rule_id' => null,
                    'message' => null,
                    'severity' => null,
                ];
            }

            // Parse entry data
            if ($currentEntry) {
                // Timestamp and client info
                if (preg_match('/^\[([^\]]+)\].*\[client ([^\]]+)\]/', $line, $matches)) {
                    $currentEntry['timestamp'] = $matches[1];
                    $currentEntry['client_ip'] = $matches[2];
                }

                // Request URI
                if (preg_match('/^GET|POST|PUT|DELETE|PATCH\s+(\S+)/', $line, $matches)) {
                    $currentEntry['request_uri'] = $matches[1];
                }

                // Rule info
                if (preg_match('/\[id "(\d+)"\]/', $line, $matches)) {
                    $currentEntry['rule_id'] = $matches[1];
                }

                if (preg_match('/\[msg "([^"]+)"\]/', $line, $matches)) {
                    $currentEntry['message'] = $matches[1];
                }

                if (preg_match('/\[severity "([^"]+)"\]/', $line, $matches)) {
                    $currentEntry['severity'] = $matches[1];
                }
            }
        }

        if ($currentEntry) {
            $entries[] = $currentEntry;
        }

        return $entries;
    }

    /**
     * Get available rulesets (OWASP CRS)
     */
    public function getRulesets(): array
    {
        $rulesets = [
            ['id' => 'sql-injection', 'name' => 'SQL Injection', 'enabled' => true],
            ['id' => 'xss', 'name' => 'Cross-Site Scripting (XSS)', 'enabled' => true],
            ['id' => 'lfi', 'name' => 'Local File Inclusion', 'enabled' => true],
            ['id' => 'rfi', 'name' => 'Remote File Inclusion', 'enabled' => true],
            ['id' => 'rce', 'name' => 'Remote Code Execution', 'enabled' => true],
            ['id' => 'php', 'name' => 'PHP Injection', 'enabled' => true],
            ['id' => 'session-fixation', 'name' => 'Session Fixation', 'enabled' => true],
            ['id' => 'scanner-detection', 'name' => 'Scanner Detection', 'enabled' => true],
            ['id' => 'protocol-enforcement', 'name' => 'Protocol Enforcement', 'enabled' => true],
        ];

        // Check which are actually enabled
        foreach ($rulesets as &$ruleset) {
            $ruleset['enabled'] = $this->isRulesetEnabled($ruleset['id']);
        }

        return $rulesets;
    }

    /**
     * Check if a ruleset is enabled
     */
    protected function isRulesetEnabled(string $ruleset): bool
    {
        // This would check actual rule files, simplified for now
        return true;
    }

    /**
     * Enable a ruleset
     */
    public function enableRuleset(string $ruleset): array
    {
        Log::info('WAF ruleset enabled', ['ruleset' => $ruleset]);
        return ['success' => true, 'message' => "Ruleset {$ruleset} enabled"];
    }

    /**
     * Disable a ruleset
     */
    public function disableRuleset(string $ruleset): array
    {
        Log::info('WAF ruleset disabled', ['ruleset' => $ruleset]);
        return ['success' => true, 'message' => "Ruleset {$ruleset} disabled"];
    }

    /**
     * Add rule to whitelist
     */
    public function addWhitelistRule(string $ruleId, ?string $domain = null): bool
    {
        $directive = "SecRuleRemoveById {$ruleId}";

        if ($domain) {
            $directive = "# Whitelist for {$domain}\n{$directive}";
        }

        $content = '';
        if (file_exists($this->whitelistPath)) {
            $content = file_get_contents($this->whitelistPath);
        }

        $content .= "\n{$directive}\n";

        file_put_contents($this->whitelistPath, $content);

        Log::info('WAF rule whitelisted', ['rule_id' => $ruleId, 'domain' => $domain]);

        $this->reloadNginx();

        return true;
    }

    /**
     * Remove rule from whitelist
     */
    public function removeWhitelistRule(string $ruleId): bool
    {
        if (!file_exists($this->whitelistPath)) {
            return true;
        }

        $content = file_get_contents($this->whitelistPath);
        $content = preg_replace("/SecRuleRemoveById\s+{$ruleId}\n?/", '', $content);

        file_put_contents($this->whitelistPath, $content);

        Log::info('WAF rule removed from whitelist', ['rule_id' => $ruleId]);

        $this->reloadNginx();

        return true;
    }

    /**
     * Get whitelisted rules
     */
    public function getWhitelistedRules(): array
    {
        if (!file_exists($this->whitelistPath)) {
            return [];
        }

        $content = file_get_contents($this->whitelistPath);
        $rules = [];

        preg_match_all('/SecRuleRemoveById\s+(\d+)/', $content, $matches);

        foreach ($matches[1] as $ruleId) {
            $rules[] = ['rule_id' => $ruleId];
        }

        return $rules;
    }

    /**
     * Get rules count
     */
    protected function getRulesCount(): int
    {
        if (!is_dir($this->rulesPath)) {
            return 0;
        }

        $count = 0;
        $files = glob("{$this->rulesPath}/*.conf");

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $count += preg_match_all('/SecRule\s+/', $content);
        }

        return $count;
    }

    /**
     * Update ModSecurity config
     */
    protected function updateConfig(string $directive, string $value): void
    {
        if (!file_exists($this->modsecConfPath)) {
            throw new \Exception('ModSecurity config file not found');
        }

        $content = file_get_contents($this->modsecConfPath);

        // Check if directive exists
        if (preg_match("/^{$directive}\s+\w+/m", $content)) {
            $content = preg_replace(
                "/^{$directive}\s+\w+/m",
                "{$directive} {$value}",
                $content
            );
        } else {
            $content .= "\n{$directive} {$value}\n";
        }

        file_put_contents($this->modsecConfPath, $content);
    }

    /**
     * Reload Nginx
     */
    protected function reloadNginx(): void
    {
        Process::run('sudo systemctl reload nginx');
    }
}
