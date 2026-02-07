<?php

declare(strict_types=1);

namespace App\Modules\Firewall\Services;

use App\Modules\Firewall\Models\FirewallRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class FirewallService
{
    /**
     * Essential ports that should always be allowed
     */
    protected array $essentialRules = [
        ['port' => '22', 'protocol' => 'tcp', 'comment' => 'SSH'],
        ['port' => '80', 'protocol' => 'tcp', 'comment' => 'HTTP'],
        ['port' => '443', 'protocol' => 'tcp', 'comment' => 'HTTPS'],
        ['port' => '8000', 'protocol' => 'tcp', 'comment' => 'VSISPanel'],
        ['port' => '21', 'protocol' => 'tcp', 'comment' => 'FTP Command'],
        ['port' => '20', 'protocol' => 'tcp', 'comment' => 'FTP Data'],
        ['port' => '40000:40100', 'protocol' => 'tcp', 'comment' => 'FTP Passive'],
        ['port' => '25', 'protocol' => 'tcp', 'comment' => 'SMTP'],
        ['port' => '587', 'protocol' => 'tcp', 'comment' => 'SMTP Submission'],
        ['port' => '465', 'protocol' => 'tcp', 'comment' => 'SMTPS'],
        ['port' => '993', 'protocol' => 'tcp', 'comment' => 'IMAPS'],
        ['port' => '995', 'protocol' => 'tcp', 'comment' => 'POP3S'],
        ['port' => '143', 'protocol' => 'tcp', 'comment' => 'IMAP'],
        ['port' => '110', 'protocol' => 'tcp', 'comment' => 'POP3'],
        ['port' => '53', 'protocol' => 'tcp', 'comment' => 'DNS TCP'],
        ['port' => '53', 'protocol' => 'udp', 'comment' => 'DNS UDP'],
        ['port' => '3306', 'protocol' => 'tcp', 'comment' => 'MySQL (localhost only)', 'source_ip' => '127.0.0.1'],
    ];

    /**
     * Enable firewall
     */
    public function enable(): array
    {
        $result = $this->runCommand('ufw --force enable');

        if ($result['success']) {
            Log::info('Firewall enabled');
        }

        return $result;
    }

    /**
     * Disable firewall
     */
    public function disable(): array
    {
        $result = $this->runCommand('ufw --force disable');

        if ($result['success']) {
            Log::warning('Firewall disabled');
        }

        return $result;
    }

    /**
     * Get firewall status
     */
    public function getStatus(): array
    {
        $result = $this->runCommand('ufw status verbose');

        if (!$result['success']) {
            return [
                'enabled' => false,
                'error' => $result['output'],
            ];
        }

        $output = $result['output'];
        $lines = explode("\n", $output);

        $status = [
            'enabled' => str_contains($output, 'Status: active'),
            'default_incoming' => 'deny',
            'default_outgoing' => 'allow',
            'default_routed' => 'disabled',
            'rules' => [],
        ];

        // Parse default policies
        foreach ($lines as $line) {
            if (preg_match('/Default:\s*(\w+)\s*\(incoming\),\s*(\w+)\s*\(outgoing\),\s*(\w+)\s*\(routed\)/i', $line, $matches)) {
                $status['default_incoming'] = strtolower($matches[1]);
                $status['default_outgoing'] = strtolower($matches[2]);
                $status['default_routed'] = strtolower($matches[3]);
            }
        }

        return $status;
    }

    /**
     * Get parsed rules from UFW
     */
    public function getUfwRules(): array
    {
        $result = $this->runCommand('ufw status numbered');

        if (!$result['success']) {
            return [];
        }

        $rules = [];
        $lines = explode("\n", $result['output']);

        foreach ($lines as $line) {
            // Parse lines like: [ 1] 22/tcp                     ALLOW IN    Anywhere
            if (preg_match('/\[\s*(\d+)\]\s+(.+?)\s+(ALLOW|DENY|LIMIT|REJECT)\s+(IN|OUT)?\s*(.*)/', $line, $matches)) {
                $ruleNumber = (int)$matches[1];
                $portProto = trim($matches[2]);
                $action = strtolower($matches[3]);
                $direction = strtolower($matches[4] ?? 'in');
                $source = trim($matches[5]);

                // Parse port/protocol
                $port = null;
                $protocol = 'any';
                if (preg_match('/^(\d+(?::\d+)?(?:,\d+)*)(?:\/(\w+))?/', $portProto, $portMatch)) {
                    $port = $portMatch[1];
                    $protocol = $portMatch[2] ?? 'any';
                }

                // Parse source IP
                $sourceIp = null;
                if ($source && $source !== 'Anywhere' && $source !== 'Anywhere (v6)') {
                    $sourceIp = $source;
                }

                $rules[] = [
                    'number' => $ruleNumber,
                    'port' => $port,
                    'protocol' => $protocol,
                    'action' => $action,
                    'direction' => $direction ?: 'in',
                    'source_ip' => $sourceIp,
                    'raw' => $line,
                ];
            }
        }

        return $rules;
    }

    /**
     * Add a firewall rule
     */
    public function addRule(array $data): FirewallRule
    {
        // Create the rule in database first
        $rule = FirewallRule::create([
            'action' => $data['action'] ?? 'allow',
            'direction' => $data['direction'] ?? 'in',
            'protocol' => $data['protocol'] ?? 'any',
            'port' => $data['port'] ?? null,
            'source_ip' => $data['source_ip'] ?? null,
            'destination_ip' => $data['destination_ip'] ?? null,
            'comment' => $data['comment'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_essential' => $data['is_essential'] ?? false,
            'priority' => $data['priority'] ?? 100,
        ]);

        // Apply to UFW if active
        if ($rule->is_active) {
            $this->applyRuleToUfw($rule);
        }

        Log::info('Firewall rule added', ['rule_id' => $rule->id, 'port' => $rule->port]);

        return $rule;
    }

    /**
     * Apply a rule to UFW
     */
    protected function applyRuleToUfw(FirewallRule $rule): array
    {
        $command = $rule->buildUfwCommand();
        return $this->runCommand($command);
    }

    /**
     * Delete a firewall rule
     */
    public function deleteRule(FirewallRule $rule): bool
    {
        if ($rule->is_essential) {
            throw new \Exception('Cannot delete essential firewall rule');
        }

        // Delete from UFW if rule number is known
        if ($rule->ufw_rule_number) {
            // Need to delete by rule number (requires confirmation bypass)
            $this->runCommand("ufw --force delete {$rule->ufw_rule_number}");
        } else {
            // Try to delete by matching the rule specification
            $this->deleteRuleFromUfwBySpec($rule);
        }

        // Soft delete from database
        $rule->delete();

        Log::info('Firewall rule deleted', ['rule_id' => $rule->id]);

        return true;
    }

    /**
     * Delete rule from UFW by specification
     */
    protected function deleteRuleFromUfwBySpec(FirewallRule $rule): array
    {
        $parts = ['ufw', '--force', 'delete'];
        $parts[] = $rule->action;

        if ($rule->direction === 'out') {
            $parts[] = 'out';
        }

        if ($rule->protocol && $rule->protocol !== 'any') {
            $parts[] = 'proto';
            $parts[] = $rule->protocol;
        }

        if ($rule->source_ip) {
            $parts[] = 'from';
            $parts[] = $rule->source_ip;
        }

        if ($rule->port) {
            $parts[] = 'to';
            $parts[] = 'any';
            $parts[] = 'port';
            $parts[] = $rule->port;
        }

        $command = implode(' ', $parts);
        return $this->runCommand($command);
    }

    /**
     * Toggle rule active status
     */
    public function toggleRule(FirewallRule $rule): FirewallRule
    {
        if ($rule->is_active) {
            // Deactivate - remove from UFW
            $this->deleteRuleFromUfwBySpec($rule);
            $rule->is_active = false;
        } else {
            // Activate - add to UFW
            $this->applyRuleToUfw($rule);
            $rule->is_active = true;
        }

        $rule->save();

        Log::info('Firewall rule toggled', ['rule_id' => $rule->id, 'is_active' => $rule->is_active]);

        return $rule;
    }

    /**
     * Get default policies
     */
    public function getDefaultPolicies(): array
    {
        $status = $this->getStatus();

        return [
            'incoming' => $status['default_incoming'] ?? 'deny',
            'outgoing' => $status['default_outgoing'] ?? 'allow',
            'routed' => $status['default_routed'] ?? 'disabled',
        ];
    }

    /**
     * Set default policy
     */
    public function setDefaultPolicy(string $direction, string $policy): array
    {
        if (!in_array($direction, ['incoming', 'outgoing', 'routed'])) {
            throw new \InvalidArgumentException('Invalid direction');
        }

        if (!in_array($policy, ['allow', 'deny', 'reject'])) {
            throw new \InvalidArgumentException('Invalid policy');
        }

        $result = $this->runCommand("ufw default {$policy} {$direction}");

        if ($result['success']) {
            Log::info('Firewall default policy changed', compact('direction', 'policy'));
        }

        return $result;
    }

    /**
     * Get all rules from database
     */
    public function getRulesList(): Collection
    {
        return FirewallRule::ordered()->get();
    }

    /**
     * Sync rules with UFW status
     */
    public function syncRulesWithUfw(): void
    {
        $ufwRules = $this->getUfwRules();
        $dbRules = FirewallRule::active()->get();

        // Update rule numbers from UFW
        foreach ($dbRules as $dbRule) {
            foreach ($ufwRules as $ufwRule) {
                if ($this->rulesMatch($dbRule, $ufwRule)) {
                    $dbRule->ufw_rule_number = $ufwRule['number'];
                    $dbRule->save();
                    break;
                }
            }
        }
    }

    /**
     * Check if database rule matches UFW rule
     */
    protected function rulesMatch(FirewallRule $dbRule, array $ufwRule): bool
    {
        return $dbRule->port === $ufwRule['port']
            && $dbRule->protocol === $ufwRule['protocol']
            && $dbRule->action === $ufwRule['action']
            && $dbRule->direction === $ufwRule['direction'];
    }

    /**
     * Reset firewall to defaults
     */
    public function resetToDefaults(): array
    {
        // Reset UFW
        $result = $this->runCommand('ufw --force reset');

        if (!$result['success']) {
            return $result;
        }

        // Remove non-essential rules from database
        FirewallRule::where('is_essential', false)->delete();

        // Re-enable firewall
        $this->enable();

        // Re-apply essential rules
        $this->createEssentialRules();

        Log::info('Firewall reset to defaults');

        return ['success' => true, 'message' => 'Firewall reset to defaults'];
    }

    /**
     * Create essential rules on first setup
     */
    public function createEssentialRules(): void
    {
        foreach ($this->essentialRules as $ruleData) {
            $exists = FirewallRule::where('port', $ruleData['port'])
                ->where('protocol', $ruleData['protocol'])
                ->where('is_essential', true)
                ->exists();

            if (!$exists) {
                $this->addRule([
                    'action' => 'allow',
                    'direction' => 'in',
                    'protocol' => $ruleData['protocol'],
                    'port' => $ruleData['port'],
                    'source_ip' => $ruleData['source_ip'] ?? null,
                    'comment' => $ruleData['comment'],
                    'is_essential' => true,
                    'priority' => 10,
                ]);
            }
        }
    }

    /**
     * Check if firewall is enabled
     */
    public function isEnabled(): bool
    {
        $status = $this->getStatus();
        return $status['enabled'] ?? false;
    }

    /**
     * Quick add: Allow IP
     */
    public function allowIp(string $ip, ?string $comment = null): FirewallRule
    {
        return $this->addRule([
            'action' => 'allow',
            'direction' => 'in',
            'protocol' => 'any',
            'source_ip' => $ip,
            'comment' => $comment ?? "Allow IP: {$ip}",
        ]);
    }

    /**
     * Quick add: Block IP
     */
    public function blockIp(string $ip, ?string $comment = null): FirewallRule
    {
        return $this->addRule([
            'action' => 'deny',
            'direction' => 'in',
            'protocol' => 'any',
            'source_ip' => $ip,
            'comment' => $comment ?? "Block IP: {$ip}",
            'priority' => 1, // High priority to block before other rules
        ]);
    }

    /**
     * Quick add: Allow port
     */
    public function allowPort(string $port, string $protocol = 'tcp', ?string $comment = null): FirewallRule
    {
        return $this->addRule([
            'action' => 'allow',
            'direction' => 'in',
            'protocol' => $protocol,
            'port' => $port,
            'comment' => $comment ?? "Allow port {$port}/{$protocol}",
        ]);
    }

    /**
     * Quick add: Deny port
     */
    public function denyPort(string $port, string $protocol = 'tcp', ?string $comment = null): FirewallRule
    {
        return $this->addRule([
            'action' => 'deny',
            'direction' => 'in',
            'protocol' => $protocol,
            'port' => $port,
            'comment' => $comment ?? "Deny port {$port}/{$protocol}",
        ]);
    }

    /**
     * Run a shell command
     */
    protected function runCommand(string $command): array
    {
        Log::debug('Running firewall command', ['command' => $command]);

        try {
            $result = Process::timeout(30)->run($command);

            return [
                'success' => $result->successful(),
                'output' => $result->output(),
                'error' => $result->errorOutput(),
                'exit_code' => $result->exitCode(),
            ];
        } catch (\Exception $e) {
            Log::error('Firewall command failed', [
                'command' => $command,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'exit_code' => -1,
            ];
        }
    }
}
