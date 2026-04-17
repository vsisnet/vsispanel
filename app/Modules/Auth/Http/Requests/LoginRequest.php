<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    public function prepareForValidation(): void
    {
        // Accept 'login' or 'email' or 'username' field - use whichever is provided
        if (!$this->has('login')) {
            $login = $this->input('username') ?: $this->input('email');
            if ($login) $this->merge(['login' => $login]);
        }
    }

    public function getLoginField(): string
    {
        return $this->input('login', $this->input('username', $this->input('email', '')));
    }
}
