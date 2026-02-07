<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id ?? null;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'username' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'password' => ['sometimes', 'nullable', 'string', Password::min(8)->mixedCase()->numbers()],
            'role' => ['sometimes', 'string', Rule::in(['admin', 'reseller', 'user'])],
            'status' => ['sometimes', 'string', Rule::in(['active', 'suspended'])],
            'locale' => ['sometimes', 'nullable', 'string', 'max:10'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username can only contain letters, numbers, and underscores.',
        ];
    }
}
