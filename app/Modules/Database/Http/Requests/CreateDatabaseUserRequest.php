<?php

declare(strict_types=1);

namespace App\Modules\Database\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDatabaseUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:1',
                'max:16',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:64',
            ],
            'host' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Username is required.',
            'username.regex' => 'Username must start with a letter and contain only letters, numbers, and underscores.',
            'username.max' => 'Username cannot exceed 16 characters.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
        ];
    }
}
