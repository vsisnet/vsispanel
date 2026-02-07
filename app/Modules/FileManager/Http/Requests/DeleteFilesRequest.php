<?php

declare(strict_types=1);

namespace App\Modules\FileManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteFilesRequest extends FormRequest
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
        ];
    }

    public function messages(): array
    {
        return [
            'paths.required' => 'At least one path is required.',
            'paths.*.required' => 'Path cannot be empty.',
        ];
    }
}
