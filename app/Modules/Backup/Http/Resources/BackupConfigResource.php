<?php

declare(strict_types=1);

namespace App\Modules\Backup\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackupConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Sanitize destination config - don't expose sensitive data
        $destinationConfig = $this->destination_config;
        $sanitizedConfig = [];

        switch ($this->destination_type) {
            case 'local':
                $sanitizedConfig = [
                    'path' => $destinationConfig['path'] ?? null,
                ];
                break;
            case 's3':
                $sanitizedConfig = [
                    'bucket' => $destinationConfig['bucket'] ?? null,
                    'path' => $destinationConfig['path'] ?? null,
                    'region' => $destinationConfig['region'] ?? null,
                    'endpoint' => $destinationConfig['endpoint'] ?? null,
                    'has_credentials' => !empty($destinationConfig['access_key']),
                ];
                break;
            case 'ftp':
                $sanitizedConfig = [
                    'host' => $destinationConfig['host'] ?? null,
                    'path' => $destinationConfig['path'] ?? null,
                    'port' => $destinationConfig['port'] ?? 21,
                    'use_sftp' => $destinationConfig['use_sftp'] ?? false,
                    'has_credentials' => !empty($destinationConfig['username']),
                ];
                break;
            case 'b2':
                $sanitizedConfig = [
                    'bucket_name' => $destinationConfig['bucket_name'] ?? null,
                    'path' => $destinationConfig['path'] ?? null,
                    'has_credentials' => !empty($destinationConfig['account_id']),
                ];
                break;
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'type' => $this->type,
            'backup_items' => $this->backup_items ?? [],
            'destination_type' => $this->destination_type,
            'destinations' => $this->destinations ?? [$this->destination_type],
            'storage_remote_id' => $this->storage_remote_id,
            'destination_config' => $sanitizedConfig,
            'schedule' => $this->schedule,
            'schedule_time' => $this->schedule_time,
            'schedule_day' => $this->schedule_day,
            'schedule_cron' => $this->schedule_cron,
            'retention_policy' => $this->retention_policy,
            'include_paths' => $this->include_paths,
            'exclude_patterns' => $this->exclude_patterns,
            'is_active' => $this->is_active,
            'last_run_at' => $this->last_run_at?->toISOString(),
            'next_run_at' => $this->next_run_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'latest_backup' => $this->whenLoaded('backups', function () {
                $latest = $this->latestBackup();
                return $latest ? new BackupResource($latest) : null;
            }),
            'backup_count' => $this->whenCounted('backups'),
        ];
    }
}
