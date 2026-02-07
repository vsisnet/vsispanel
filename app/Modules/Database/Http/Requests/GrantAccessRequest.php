<?php

declare(strict_types=1);

namespace App\Modules\Database\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GrantAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'database_id' => [
                'required',
                'uuid',
                'exists:managed_databases,id',
            ],
            'privileges' => [
                'nullable',
                'array',
            ],
            'privileges.*' => [
                'string',
                'in:SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,ALTER,INDEX,CREATE TEMPORARY TABLES,LOCK TABLES,EXECUTE,CREATE VIEW,SHOW VIEW,CREATE ROUTINE,ALTER ROUTINE,EVENT,TRIGGER,REFERENCES',
            ],
        ];
    }
}
