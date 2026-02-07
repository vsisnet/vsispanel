<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'uuid',
                'exists:users,id',
            ],
            'plan_id' => [
                'required',
                'uuid',
                'exists:plans,id',
            ],
            'expires_at' => [
                'nullable',
                'date',
                'after:today',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User is required.',
            'user_id.exists' => 'Selected user does not exist.',
            'plan_id.required' => 'Plan is required.',
            'plan_id.exists' => 'Selected plan does not exist.',
            'expires_at.after' => 'Expiration date must be in the future.',
        ];
    }
}
