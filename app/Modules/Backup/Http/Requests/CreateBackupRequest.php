<?php

declare(strict_types=1);

namespace App\Modules\Backup\Http\Requests;

use App\Modules\Backup\Models\Backup;
use Illuminate\Foundation\Http\FormRequest;

class CreateBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'backup_config_id' => 'required|uuid|exists:backup_configs,id',
            'type' => 'sometimes|in:' . implode(',', [
                Backup::TYPE_FULL,
                Backup::TYPE_FILES,
                Backup::TYPE_DATABASES,
                Backup::TYPE_EMAILS,
                Backup::TYPE_CONFIG,
            ]),
        ];
    }
}
