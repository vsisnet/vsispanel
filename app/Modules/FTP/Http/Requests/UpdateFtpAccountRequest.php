<?php

declare(strict_types=1);

namespace App\Modules\FTP\Http\Requests;

use App\Modules\FTP\Models\FtpAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFtpAccountRequest extends FormRequest
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
        $accountId = $this->route('ftpAccount')?->id ?? $this->route('ftp_account');

        return [
            'username' => [
                'sometimes',
                'string',
                'min:3',
                'max:32',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]{2,31}$/',
                Rule::unique('ftp_accounts', 'username')->ignore($accountId),
            ],
            'password' => ['sometimes', 'string', 'min:8', 'max:128'],
            'home_directory' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(FtpAccount::getStatuses())],
            'quota_mb' => ['nullable', 'integer', 'min:0', 'max:10485760'],
            'bandwidth_mb' => ['nullable', 'integer', 'min:0'],
            'upload_bandwidth_kbps' => ['nullable', 'integer', 'min:0', 'max:1048576'],
            'download_bandwidth_kbps' => ['nullable', 'integer', 'min:0', 'max:1048576'],
            'max_connections' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_connections_per_ip' => ['nullable', 'integer', 'min:1', 'max:100'],
            'allowed_ips' => ['nullable', 'array'],
            'allowed_ips.*' => ['ip'],
            'denied_ips' => ['nullable', 'array'],
            'denied_ips.*' => ['ip'],
            'allow_upload' => ['nullable', 'boolean'],
            'allow_download' => ['nullable', 'boolean'],
            'allow_mkdir' => ['nullable', 'boolean'],
            'allow_delete' => ['nullable', 'boolean'],
            'allow_rename' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Username must start with a letter and contain only letters, numbers, and underscores.',
            'username.unique' => 'This username is already taken.',
            'password.min' => 'Password must be at least 8 characters.',
            'expires_at.after' => 'Expiration date must be in the future.',
        ];
    }
}
