<?php

declare(strict_types=1);

namespace App\Modules\SSL\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadCustomCertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'certificate' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!str_contains($value, '-----BEGIN CERTIFICATE-----')) {
                        $fail('The certificate must be in PEM format.');
                    }
                },
            ],
            'private_key' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!str_contains($value, '-----BEGIN') || !str_contains($value, 'PRIVATE KEY-----')) {
                        $fail('The private key must be in PEM format.');
                    }
                },
            ],
            'ca_bundle' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if ($value && !str_contains($value, '-----BEGIN CERTIFICATE-----')) {
                        $fail('The CA bundle must be in PEM format.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'certificate.required' => 'SSL certificate is required.',
            'private_key.required' => 'Private key is required.',
        ];
    }
}
