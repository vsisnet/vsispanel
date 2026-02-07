<?php

declare(strict_types=1);

namespace App\Modules\WebServer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePhpSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'memory_limit' => [
                'sometimes',
                'string',
                'regex:/^\d+[KMG]?$/i',
            ],
            'upload_max_filesize' => [
                'sometimes',
                'string',
                'regex:/^\d+[KMG]?$/i',
            ],
            'post_max_size' => [
                'sometimes',
                'string',
                'regex:/^\d+[KMG]?$/i',
            ],
            'max_execution_time' => [
                'sometimes',
                'integer',
                'min:0',
                'max:86400', // Up to 24 hours, 0 = unlimited
            ],
            'max_input_time' => [
                'sometimes',
                'integer',
                'min:-1', // -1 = use max_execution_time value
                'max:86400',
            ],
            'display_errors' => [
                'sometimes',
                'string',
                'in:on,off,0,1',
            ],
            'log_errors' => [
                'sometimes',
                'string',
                'in:on,off,0,1',
            ],
            'max_input_vars' => [
                'sometimes',
                'integer',
                'min:100',
                'max:100000', // Higher limit for complex applications like WooCommerce
            ],
            'date.timezone' => [
                'sometimes',
                'string',
                'timezone',
            ],
            'session.gc_maxlifetime' => [
                'sometimes',
                'integer',
                'min:60',
                'max:86400',
            ],
            'session.cookie_lifetime' => [
                'sometimes',
                'integer',
                'min:0',
                'max:86400',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'memory_limit.regex' => 'Invalid memory_limit format. Use format like 256M, 1G, etc.',
            'upload_max_filesize.regex' => 'Invalid upload_max_filesize format. Use format like 64M, 1G, etc.',
            'post_max_size.regex' => 'Invalid post_max_size format. Use format like 64M, 1G, etc.',
            'max_execution_time.max' => 'max_execution_time cannot exceed 86400 seconds (24 hours). Use 0 for unlimited.',
            'max_input_time.max' => 'max_input_time cannot exceed 86400 seconds (24 hours). Use -1 to inherit max_execution_time.',
            'display_errors.in' => 'display_errors must be on, off, 0, or 1.',
            'max_input_vars.max' => 'max_input_vars cannot exceed 100000.',
        ];
    }
}
