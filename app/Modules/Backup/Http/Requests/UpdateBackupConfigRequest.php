<?php

declare(strict_types=1);

namespace App\Modules\Backup\Http\Requests;

use App\Modules\Backup\Models\BackupConfig;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBackupConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:' . implode(',', BackupConfig::getBackupTypes()),
            'backup_items' => 'nullable|array',
            'backup_items.*' => 'string|in:files,databases,emails,config',
            'destination_type' => 'sometimes|in:' . implode(',', BackupConfig::getDestinationTypes()),
            'destinations' => 'nullable|array',
            'destinations.*' => 'string|max:100',
            'storage_remote_id' => 'nullable|uuid|exists:storage_remotes,id',
            'destination_config' => 'sometimes|array',
            'destination_config.password' => 'sometimes|string',
            'destination_config.path' => 'sometimes|string',
            'schedule' => 'nullable|string|max:100',
            'schedule_time' => ['nullable', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'schedule_day' => 'nullable|string|max:10',
            'schedule_cron' => 'nullable|string|max:100',
            'retention_policy' => 'nullable|array',
            'retention_policy.keep_last' => 'nullable|integer|min:1|max:100',
            'retention_policy.keep_daily' => 'nullable|integer|min:0|max:365',
            'retention_policy.keep_weekly' => 'nullable|integer|min:0|max:52',
            'retention_policy.keep_monthly' => 'nullable|integer|min:0|max:24',
            'retention_policy.keep_yearly' => 'nullable|integer|min:0|max:10',
            'include_paths' => 'nullable|array',
            'include_paths.*' => 'string|max:500',
            'exclude_patterns' => 'nullable|array',
            'exclude_patterns.*' => 'string|max:255',
            'is_active' => 'boolean',
        ];
    }
}
