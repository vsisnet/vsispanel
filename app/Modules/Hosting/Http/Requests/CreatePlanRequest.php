<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePlanRequest extends FormRequest
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
                'max:255',
                'unique:plans,name',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'disk_limit' => [
                'required',
                'integer',
                'min:0',
            ],
            'bandwidth_limit' => [
                'required',
                'integer',
                'min:0',
            ],
            'domains_limit' => [
                'required',
                'integer',
                'min:0',
            ],
            'subdomains_limit' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'databases_limit' => [
                'required',
                'integer',
                'min:0',
            ],
            'email_accounts_limit' => [
                'required',
                'integer',
                'min:0',
            ],
            'ftp_accounts_limit' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'php_version_default' => [
                'nullable',
                'string',
                'in:8.1,8.2,8.3',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Plan name is required.',
            'name.unique' => 'A plan with this name already exists.',
            'disk_limit.required' => 'Disk limit is required.',
            'bandwidth_limit.required' => 'Bandwidth limit is required.',
            'domains_limit.required' => 'Domains limit is required.',
            'databases_limit.required' => 'Databases limit is required.',
            'email_accounts_limit.required' => 'Email accounts limit is required.',
        ];
    }
}
