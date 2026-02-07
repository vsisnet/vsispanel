<?php

declare(strict_types=1);

namespace App\Modules\FileManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = config('filemanager.max_upload_size', 104857600) / 1024; // Convert to KB

        return [
            'path' => ['nullable', 'string', 'max:1000'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', "max:{$maxSize}"],
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'At least one file is required.',
            'files.*.max' => 'File size exceeds the maximum allowed size.',
        ];
    }
}
