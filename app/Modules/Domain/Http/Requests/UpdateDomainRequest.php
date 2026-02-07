<?php

declare(strict_types=1);

namespace App\Modules\Domain\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
            'php_version.in' => 'Invalid PHP version selected.',
            'web_server_type.in' => 'Invalid web server type.',
        ];
    }
}
