<?php

declare(strict_types=1);

namespace App\Modules\Mail\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMailAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'quota_mb' => ['nullable', 'integer', 'min:1', 'max:102400'],
            'status' => ['nullable', 'in:active,suspended,disabled'],
        ];
    }
}
