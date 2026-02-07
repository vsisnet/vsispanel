<?php

declare(strict_types=1);

namespace App\Modules\Database\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:1',
                'max:32',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
            ],
            'domain_id' => [
                'nullable',
                'uuid',
                'exists:domains,id',
            ],
            'charset' => [
                'nullable',
                'string',
                'in:utf8,utf8mb4,latin1,utf16,utf32',
            ],
            'collation' => [
                'nullable',
                'string',
                'regex:/^[a-zA-Z0-9_]+$/',
            ],
            // Create user options
            'create_user' => [
                'nullable',
                'boolean',
            ],
            'username' => [
                'required_if:create_user,true',
                'nullable',
                'string',
                'min:1',
                'max:32',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
            ],
            'password' => [
                'required_if:create_user,true',
                'nullable',
                'string',
                'min:8',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Database name is required.',
            'name.regex' => 'Database name must start with a letter and contain only letters, numbers, and underscores.',
            'name.max' => 'Database name cannot exceed 32 characters.',
            'username.required_if' => 'Username is required when creating a database user.',
            'username.regex' => 'Username must start with a letter and contain only letters, numbers, and underscores.',
            'password.required_if' => 'Password is required when creating a database user.',
            'password.min' => 'Password must be at least 8 characters.',
        ];
    }
}
