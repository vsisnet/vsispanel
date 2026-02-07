<?php

declare(strict_types=1);

namespace App\Modules\Database\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:262144', // 256MB
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    $allowedExtensions = ['sql', 'gz', 'zip'];
                    if (!in_array($extension, $allowedExtensions)) {
                        $fail('File must be a .sql, .gz, or .zip file.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'SQL file is required.',
            'file.mimes' => 'File must be a .sql, .gz, or .zip file.',
            'file.max' => 'File size cannot exceed 100MB.',
        ];
    }
}
