<?php

declare(strict_types=1);

namespace App\Modules\FileManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExtractArchiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:1000'],
            'destination' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'path.required' => 'Archive path is required.',
        ];
    }
}
