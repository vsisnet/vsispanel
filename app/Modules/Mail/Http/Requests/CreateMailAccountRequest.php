<?php

declare(strict_types=1);

namespace App\Modules\Mail\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateMailAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'mail_domain_id' => ['required', 'uuid', 'exists:mail_domains,id'],
            'username' => ['required', 'string', 'min:1', 'max:64', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'quota_mb' => ['nullable', 'integer', 'min:1', 'max:102400'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Username can only contain letters, numbers, dots, underscores and hyphens.',
        ];
    }
}
