<?php

declare(strict_types=1);

namespace App\Modules\FileManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:1000'],
            'new_name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[^\/\\\\]+$/', // No slashes allowed
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'path.required' => 'File path is required.',
            'new_name.required' => 'New name is required.',
            'new_name.regex' => 'Invalid filename. Slashes are not allowed.',
        ];
    }
}
