<?php

declare(strict_types=1);

namespace App\Modules\Security\Services;

use App\Modules\Firewall\Models\FirewallRule;
use App\Modules\Firewall\Services\Fail2BanService;
use App\Modules\Firewall\Services\FirewallService;
use App\Modules\Firewall\Services\WafService;
use Illuminate\Support\Facades\Cache;

class SecurityScoreService
{
    private const CACHE_KEY = 'security_score';
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly FirewallService $firewallService,
        private readonly Fail2BanService $fail2BanService,
        private readonly WafService $wafService
    ) {}

    /**
     * Calculate overall security score (0-100)
     */
    public function calculateScore(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $checks = $this->runSecurityChecks();
            $score = $this->computeScore($checks);
            $grade = $this->getGrade($score);
            $recommendations = $this->getRecommendations($checks);

            return [
                'score' => $score,
                'grade' => $grade,
                'checks' => $checks,
                'recommendations' => $recommendations,
                'calculated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Force recalculate score
     */
    public function recalculateScore(): array
    {
        Cache::forget(self::CACHE_KEY);
        return $this->calculateScore();
    }

    /**
     * Run all security checks
     */
    private function runSecurityChecks(): array
    {
        return [
            'firewall' => $this->checkFirewall(),
            'fail2ban' => $this->checkFail2Ban(),
            'waf' => $this->checkWaf(),
            'ssl' => $this->checkSsl(),
            'ssh' => $this->checkSsh(),
            'updates' => $this->checkUpdates(),
            'passwords' => $this->checkPasswordPolicies(),
            'backup' => $this->checkBackups(),
        ];
    }

    /**
     * Check firewall status
     */
    private function checkFirewall(): array
    {
        $status = $this->firewallService->getStatus();
        $isEnabled = $status['enabled'] ?? false;
        $rulesCount = FirewallRule::where('is_active', true)->count();

        $score = 0;
        $issues = [];

        if ($isEnabled) {
            $score += 15;
        } else {
            $issues[] = 'firewall_disabled';
        }

        if ($rulesCount >= 3) {
            $score += 5;
        } else {
            $issues[] = 'insufficient_rules';
        }

        // Check for essential rules
        $hasAllEssential = FirewallRule::where('is_essential', true)->where('is_active', true)->count() >= 3;
        if ($hasAllEssential) {
            $score += 5;
        } else {
            $issues[] = 'missing_essential_rules';
        }

        return [
            'name' => 'Firewall',
            'status' => $isEnabled ? 'enabled' : 'disabled',
            'score' => $score,
            'max_score' => 25,
            'passed' => $isEnabled && count($issues) === 0,
            'issues' => $issues,
        ];
    }

    /**
     * Check Fail2Ban status
     */
    private function checkFail2Ban(): array
    {
        $status = $this->fail2BanService->getStatus();
        $isRunning = $status['running'] ?? false;
        $jailCount = $status['active_jails'] ?? 0;

        $score = 0;
        $issues = [];

        if ($isRunning) {
            $score += 10;
        } else {
            $issues[] = 'fail2ban_not_running';
        }

        if ($jailCount >= 2) {
            $score += 5;
        } elseif ($jailCount >= 1) {
            $score += 2;
        } else {
            $issues[] = 'no_active_jails';
        }

        return [
            'name' => 'Fail2Ban',
            'status' => $isRunning ? 'running' : 'stopped',
            'score' => $score,
            'max_score' => 15,
            'passed' => $isRunning && $jailCount >= 1,
            'issues' => $issues,
            'details' => [
                'active_jails' => $jailCount,
            ],
        ];
    }

    /**
     * Check WAF status
     */
    private function checkWaf(): array
    {
        $status = $this->wafService->getStatus();
        $isEnabled = $status['enabled'] ?? false;
        $mode = $status['mode'] ?? 'off';

        $score = 0;
        $issues = [];

        if ($isEnabled) {
            $score += 10;
            if ($mode === 'on') {
                $score += 5;
            } elseif ($mode === 'DetectionOnly') {
                $score += 2;
                $issues[] = 'waf_detection_only';
            }
        } else {
            $issues[] = 'waf_disabled';
        }

        return [
            'name' => 'Web Application Firewall',
            'status' => $isEnabled ? $mode : 'disabled',
            'score' => $score,
            'max_score' => 15,
            'passed' => $isEnabled && $mode === 'on',
            'issues' => $issues,
        ];
    }

    /**
     * Check SSL certificates
     */
    private function checkSsl(): array
    {
        $score = 0;
        $issues = [];

        // Check if SSL is enforced for the panel
        $sslEnabled = config('app.url') && str_starts_with(config('app.url'), 'https://');

        if ($sslEnabled) {
            $score += 10;
        } else {
            $issues[] = 'panel_not_ssl';
        }

        // Check SSL certificates expiry (simplified check)
        $certFile = '/etc/ssl/certs/ssl-cert-snakeoil.pem';
        if (file_exists($certFile)) {
            $certData = openssl_x509_parse(file_get_contents($certFile));
            if ($certData && isset($certData['validTo_time_t'])) {
                $expiresIn = $certData['validTo_time_t'] - time();
                if ($expiresIn > 30 * 86400) {
                    $score += 5;
                } elseif ($expiresIn > 7 * 86400) {
                    $score += 2;
                    $issues[] = 'ssl_expiring_soon';
                } else {
                    $issues[] = 'ssl_expired_or_expiring';
                }
            }
        }

        return [
            'name' => 'SSL/TLS',
            'status' => $sslEnabled ? 'enabled' : 'disabled',
            'score' => $score,
            'max_score' => 15,
            'passed' => $sslEnabled && count($issues) === 0,
            'issues' => $issues,
        ];
    }

    /**
     * Check SSH configuration
     */
    private function checkSsh(): array
    {
        $score = 0;
        $issues = [];

        $sshConfigFile = '/etc/ssh/sshd_config';
        if (file_exists($sshConfigFile)) {
            $config = file_get_contents($sshConfigFile);

            // Check root login disabled
            if (preg_match('/^\s*PermitRootLogin\s+(no|prohibit-password)/mi', $config)) {
                $score += 5;
            } else {
                $issues[] = 'root_login_enabled';
            }

            // Check password authentication
            if (preg_match('/^\s*PasswordAuthentication\s+no/mi', $config)) {
                $score += 3;
            } else {
                $issues[] = 'password_auth_enabled';
            }

            // Check port changed from default
            if (preg_match('/^\s*Port\s+(\d+)/mi', $config, $matches)) {
                if ((int)$matches[1] !== 22) {
                    $score += 2;
                } else {
                    $issues[] = 'ssh_default_port';
                }
            }
        }

        return [
            'name' => 'SSH Configuration',
            'status' => count($issues) === 0 ? 'secure' : 'needs_attention',
            'score' => $score,
            'max_score' => 10,
            'passed' => count($issues) === 0,
            'issues' => $issues,
        ];
    }

    /**
     * Check system updates
     */
    private function checkUpdates(): array
    {
        $score = 0;
        $issues = [];

        // Check last update time
        $aptHistoryLog = '/var/log/apt/history.log';
        if (file_exists($aptHistoryLog)) {
            $lastModified = filemtime($aptHistoryLog);
            $daysSinceUpdate = (time() - $lastModified) / 86400;

            if ($daysSinceUpdate <= 7) {
                $score += 5;
            } elseif ($daysSinceUpdate <= 30) {
                $score += 2;
                $issues[] = 'updates_over_week';
            } else {
                $issues[] = 'updates_overdue';
            }
        } else {
            $issues[] = 'cannot_check_updates';
        }

        return [
            'name' => 'System Updates',
            'status' => count($issues) === 0 ? 'up_to_date' : 'needs_update',
            'score' => $score,
            'max_score' => 5,
            'passed' => count($issues) === 0,
            'issues' => $issues,
        ];
    }

    /**
     * Check password policies
     */
    private function checkPasswordPolicies(): array
    {
        $score = 0;
        $issues = [];

        // Check if 2FA is configured
        $twoFactorEnabled = config('auth.two_factor_enabled', false);
        if ($twoFactorEnabled) {
            $score += 5;
        } else {
            $issues[] = 'two_factor_not_required';
        }

        return [
            'name' => 'Password Policies',
            'status' => count($issues) === 0 ? 'strong' : 'needs_improvement',
            'score' => $score,
            'max_score' => 5,
            'passed' => count($issues) === 0,
            'issues' => $issues,
        ];
    }

    /**
     * Check backup status
     */
    private function checkBackups(): array
    {
        $score = 0;
        $issues = [];

        // Check if there are active backup configs
        $activeConfigs = \App\Modules\Backup\Models\BackupConfig::where('is_active', true)->count();

        if ($activeConfigs > 0) {
            $score += 5;

            // Check if backups ran recently
            $recentBackup = \App\Modules\Backup\Models\Backup::where('status', 'completed')
                ->where('completed_at', '>=', now()->subDays(7))
                ->exists();

            if ($recentBackup) {
                $score += 5;
            } else {
                $issues[] = 'no_recent_backups';
            }
        } else {
            $issues[] = 'no_backup_configured';
        }

        return [
            'name' => 'Backups',
            'status' => $activeConfigs > 0 ? 'configured' : 'not_configured',
            'score' => $score,
            'max_score' => 10,
            'passed' => $activeConfigs > 0 && count($issues) === 0,
            'issues' => $issues,
        ];
    }

    /**
     * Compute total score from checks
     */
    private function computeScore(array $checks): int
    {
        $totalScore = 0;
        $maxScore = 0;

        foreach ($checks as $check) {
            $totalScore += $check['score'];
            $maxScore += $check['max_score'];
        }

        return $maxScore > 0 ? (int) round(($totalScore / $maxScore) * 100) : 0;
    }

    /**
     * Get grade based on score
     */
    private function getGrade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'F',
        };
    }

    /**
     * Get recommendations based on checks
     */
    private function getRecommendations(array $checks): array
    {
        $recommendations = [];

        foreach ($checks as $key => $check) {
            foreach ($check['issues'] as $issue) {
                $recommendations[] = [
                    'category' => $key,
                    'issue' => $issue,
                    'priority' => $this->getIssuePriority($issue),
                    'message' => $this->getIssueMessage($issue),
                    'action' => $this->getIssueAction($issue),
                ];
            }
        }

        // Sort by priority
        usort($recommendations, fn($a, $b) => $a['priority'] <=> $b['priority']);

        return $recommendations;
    }

    /**
     * Get issue priority (1=critical, 2=high, 3=medium, 4=low)
     */
    private function getIssuePriority(string $issue): int
    {
        return match ($issue) {
            'firewall_disabled', 'ssl_expired_or_expiring', 'root_login_enabled' => 1,
            'fail2ban_not_running', 'waf_disabled', 'no_backup_configured' => 2,
            'insufficient_rules', 'ssl_expiring_soon', 'updates_overdue' => 3,
            default => 4,
        };
    }

    /**
     * Get human-readable message for issue
     */
    private function getIssueMessage(string $issue): string
    {
        return match ($issue) {
            'firewall_disabled' => 'Firewall is disabled. Your server is unprotected.',
            'insufficient_rules' => 'Insufficient firewall rules configured.',
            'missing_essential_rules' => 'Some essential firewall rules are missing.',
            'fail2ban_not_running' => 'Fail2Ban service is not running.',
            'no_active_jails' => 'No Fail2Ban jails are active.',
            'waf_disabled' => 'Web Application Firewall is disabled.',
            'waf_detection_only' => 'WAF is in detection-only mode.',
            'panel_not_ssl' => 'Panel is not using HTTPS.',
            'ssl_expiring_soon' => 'SSL certificate will expire soon.',
            'ssl_expired_or_expiring' => 'SSL certificate is expired or expiring very soon.',
            'root_login_enabled' => 'SSH root login is enabled.',
            'password_auth_enabled' => 'SSH password authentication is enabled.',
            'ssh_default_port' => 'SSH is using the default port 22.',
            'updates_over_week' => 'System has not been updated in over a week.',
            'updates_overdue' => 'System updates are overdue.',
            'two_factor_not_required' => 'Two-factor authentication is not required.',
            'no_backup_configured' => 'No backup configuration is set up.',
            'no_recent_backups' => 'No successful backup in the last 7 days.',
            default => 'Security issue detected.',
        };
    }

    /**
     * Get suggested action for issue
     */
    private function getIssueAction(string $issue): string
    {
        return match ($issue) {
            'firewall_disabled' => 'Enable the firewall in Firewall settings.',
            'insufficient_rules' => 'Add more firewall rules to protect services.',
            'missing_essential_rules' => 'Reset firewall to defaults to restore essential rules.',
            'fail2ban_not_running' => 'Start the Fail2Ban service.',
            'no_active_jails' => 'Configure and enable at least one Fail2Ban jail.',
            'waf_disabled' => 'Enable Web Application Firewall in Security settings.',
            'waf_detection_only' => 'Switch WAF mode from detection to blocking.',
            'panel_not_ssl' => 'Configure SSL certificate for the panel.',
            'ssl_expiring_soon' => 'Renew your SSL certificate before it expires.',
            'ssl_expired_or_expiring' => 'Renew your SSL certificate immediately.',
            'root_login_enabled' => 'Disable root login in SSH configuration.',
            'password_auth_enabled' => 'Use SSH key authentication instead of passwords.',
            'ssh_default_port' => 'Consider changing SSH to a non-standard port.',
            'updates_over_week' => 'Run system updates soon.',
            'updates_overdue' => 'Run system updates immediately.',
            'two_factor_not_required' => 'Enable two-factor authentication requirement.',
            'no_backup_configured' => 'Set up a backup configuration.',
            'no_recent_backups' => 'Check backup configuration and run a backup.',
            default => 'Review and fix this security issue.',
        };
    }
}
