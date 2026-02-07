<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $planId = $this->route('plan')?->id;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('plans', 'name')->ignore($planId),
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'disk_limit' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
            ],
            'bandwidth_limit' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
            ],
            'domains_limit' => [
                'sometimes',
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
                'sometimes',
                'required',
                'integer',
                'min:0',
            ],
            'email_accounts_limit' => [
                'sometimes',
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
}
