<?php

declare(strict_types=1);

namespace App\Modules\FileManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:1000'],
            'permissions' => [
                'required',
                'string',
                'regex:/^0?[0-7]{3,4}$/', // e.g., "755", "0755", "0644"
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'path.required' => 'File path is required.',
            'permissions.required' => 'Permissions value is required.',
            'permissions.regex' => 'Invalid permissions format. Use octal format like 755 or 0755.',
        ];
    }
}
