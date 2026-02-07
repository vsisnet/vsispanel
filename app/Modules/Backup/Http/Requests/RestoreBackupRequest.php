<?php

declare(strict_types=1);

namespace App\Modules\Backup\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_path' => 'required|string|max:500',
            'include_paths' => 'nullable|array',
            'include_paths.*' => 'string|max:500',
        ];
    }
}
