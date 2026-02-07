<?php

declare(strict_types=1);

namespace App\Modules\FileManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompressFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'paths' => ['required', 'array', 'min:1'],
            'paths.*' => ['required', 'string', 'max:1000'],
            'archive_name' => ['required', 'string', 'max:255', 'regex:/^[^\/\\\\]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'paths.required' => 'At least one path is required.',
            'archive_name.required' => 'Archive name is required.',
            'archive_name.regex' => 'Invalid archive name. Slashes are not allowed.',
        ];
    }
}
