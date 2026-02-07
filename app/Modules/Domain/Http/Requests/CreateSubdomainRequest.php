<?php

declare(strict_types=1);

namespace App\Modules\Domain\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSubdomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $domainId = $this->route('domain')?->id ?? $this->route('domain');

        return [
            'name' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?$/',
                Rule::unique('subdomains')
                    ->where('domain_id', $domainId),
            ],
            'php_version' => [
                'sometimes',
                'string',
                Rule::in(['7.4', '8.0', '8.1', '8.2', '8.3']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Subdomain name is required.',
            'name.unique' => 'This subdomain already exists.',
            'name.regex' => 'Invalid subdomain name format. Use only letters, numbers, and hyphens.',
            'name.max' => 'Subdomain name must not exceed 63 characters.',
            'php_version.in' => 'Invalid PHP version selected.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => strtolower($this->name ?? ''),
        ]);
    }
}
