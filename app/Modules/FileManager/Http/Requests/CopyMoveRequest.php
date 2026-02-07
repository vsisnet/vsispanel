<?php

declare(strict_types=1);

namespace App\Modules\FileManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CopyMoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:1000'],
            'destination' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'source.required' => 'Source path is required.',
            'destination.required' => 'Destination path is required.',
        ];
    }
}
