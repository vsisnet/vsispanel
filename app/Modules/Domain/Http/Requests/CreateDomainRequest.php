<?php

declare(strict_types=1);

namespace App\Modules\Domain\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDomainRequest extends FormRequest
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
                'max:253',
                'unique:domains,name',
                'regex:/^(?!-)(?!.*--)[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,}$/',
            ],
            'php_version' => [
                'sometimes',
                'string',
                Rule::in(['7.4', '8.0', '8.1', '8.2', '8.3']),
            ],
            'web_server_type' => [
                'sometimes',
                'string',
                Rule::in(['nginx', 'apache']),
            ],
            'is_main' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Domain name is required.',
            'name.unique' => 'This domain name is already registered.',
            'name.regex' => 'Invalid domain name format.',
            'name.max' => 'Domain name must not exceed 253 characters.',
            'php_version.in' => 'Invalid PHP version selected.',
            'web_server_type.in' => 'Invalid web server type.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => strtolower($this->name ?? ''),
        ]);
    }
}
