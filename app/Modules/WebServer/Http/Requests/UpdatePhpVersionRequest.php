<?php

declare(strict_types=1);

namespace App\Modules\WebServer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePhpVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $availableVersions = config('webserver.php_fpm.available_versions', ['7.4', '8.0', '8.1', '8.2', '8.3']);

        return [
            'php_version' => [
                'required',
                'string',
                'in:' . implode(',', $availableVersions),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'php_version.required' => 'PHP version is required.',
            'php_version.in' => 'Invalid PHP version. Available versions: ' . implode(', ', config('webserver.php_fpm.available_versions', [])),
        ];
    }
}
