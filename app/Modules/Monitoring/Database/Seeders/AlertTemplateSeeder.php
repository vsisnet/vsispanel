<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Database\Seeders;

use App\Modules\Monitoring\Models\AlertTemplate;
use Illuminate\Database\Seeder;

class AlertTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // Resource alerts
            [
                'name' => 'CPU Usage Warning',
                'category' => 'resource',
                'metric' => 'cpu',
                'condition' => 'above',
                'threshold' => 90,
                'severity' => 'warning',
                'cooldown_minutes' => 10,
                'description' => 'Alert when CPU usage exceeds 90%',
            ],
            [
                'name' => 'CPU Usage Critical',
                'category' => 'resource',
                'metric' => 'cpu',
                'condition' => 'above',
                'threshold' => 95,
                'severity' => 'critical',
                'cooldown_minutes' => 5,
                'description' => 'Critical alert when CPU usage exceeds 95%',
            ],
            [
                'name' => 'Memory Usage Warning',
                'category' => 'resource',
                'metric' => 'memory',
                'condition' => 'above',
                'threshold' => 85,
                'severity' => 'warning',
                'cooldown_minutes' => 10,
                'description' => 'Alert when memory usage exceeds 85%',
            ],
            [
                'name' => 'Memory Usage Critical',
                'category' => 'resource',
                'metric' => 'memory',
                'condition' => 'above',
                'threshold' => 95,
                'severity' => 'critical',
                'cooldown_minutes' => 5,
                'description' => 'Critical alert when memory usage exceeds 95%',
            ],
            [
                'name' => 'Disk Usage Warning',
                'category' => 'resource',
                'metric' => 'disk',
                'condition' => 'above',
                'threshold' => 80,
                'severity' => 'warning',
                'cooldown_minutes' => 30,
                'description' => 'Alert when disk usage exceeds 80%',
            ],
            [
                'name' => 'Disk Usage Critical',
                'category' => 'resource',
                'metric' => 'disk',
                'condition' => 'above',
                'threshold' => 90,
                'severity' => 'critical',
                'cooldown_minutes' => 15,
                'description' => 'Critical alert when disk usage exceeds 90%',
            ],
            // Service alerts
            [
                'name' => 'Nginx Down',
                'category' => 'service',
                'metric' => 'service_down',
                'condition' => 'equals',
                'threshold' => 0,
                'severity' => 'critical',
                'config' => ['service_name' => 'nginx'],
                'cooldown_minutes' => 5,
                'description' => 'Alert when Nginx web server goes down',
            ],
            [
                'name' => 'MySQL Down',
                'category' => 'service',
                'metric' => 'service_down',
                'condition' => 'equals',
                'threshold' => 0,
                'severity' => 'critical',
                'config' => ['service_name' => 'mysql'],
                'cooldown_minutes' => 5,
                'description' => 'Alert when MySQL database server goes down',
            ],
            [
                'name' => 'Redis Down',
                'category' => 'service',
                'metric' => 'service_down',
                'condition' => 'equals',
                'threshold' => 0,
                'severity' => 'critical',
                'config' => ['service_name' => 'redis-server'],
                'cooldown_minutes' => 5,
                'description' => 'Alert when Redis cache server goes down',
            ],
            // Security alerts
            [
                'name' => 'SSH Brute Force Detected',
                'category' => 'security',
                'metric' => 'ssh_brute_force',
                'condition' => 'above',
                'threshold' => 10,
                'severity' => 'critical',
                'cooldown_minutes' => 15,
                'description' => 'Alert when more than 10 failed SSH login attempts detected in evaluation window',
            ],
            [
                'name' => 'Panel Intrusion Attempt',
                'category' => 'security',
                'metric' => 'panel_intrusion',
                'condition' => 'above',
                'threshold' => 5,
                'severity' => 'critical',
                'cooldown_minutes' => 15,
                'description' => 'Alert when multiple failed panel login attempts from same IP detected',
            ],
            // Backup alerts
            [
                'name' => 'Backup Job Failed',
                'category' => 'backup',
                'metric' => 'backup_failed',
                'condition' => 'above',
                'threshold' => 0,
                'severity' => 'critical',
                'cooldown_minutes' => 60,
                'description' => 'Alert when a scheduled backup job fails',
            ],
            // SSL alerts
            [
                'name' => 'SSL Expiring Soon',
                'category' => 'ssl',
                'metric' => 'ssl_expiry',
                'condition' => 'below',
                'threshold' => 14,
                'severity' => 'warning',
                'config' => ['days_before' => 14],
                'cooldown_minutes' => 1440,
                'description' => 'Alert when SSL certificate expires within 14 days',
            ],
            [
                'name' => 'SSL Expiring Critical',
                'category' => 'ssl',
                'metric' => 'ssl_expiry',
                'condition' => 'below',
                'threshold' => 7,
                'severity' => 'critical',
                'config' => ['days_before' => 7],
                'cooldown_minutes' => 720,
                'description' => 'Critical alert when SSL certificate expires within 7 days',
            ],
        ];

        foreach ($templates as $template) {
            AlertTemplate::updateOrCreate(
                ['name' => $template['name']],
                $template,
            );
        }
    }
}
