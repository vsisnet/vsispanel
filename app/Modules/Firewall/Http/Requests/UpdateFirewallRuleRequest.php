<?php

declare(strict_types=1);

namespace App\Modules\Firewall\Http\Requests;

use App\Modules\Firewall\Models\FirewallRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFirewallRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => 'sometimes|in:' . implode(',', FirewallRule::getActions()),
            'direction' => 'sometimes|in:' . implode(',', FirewallRule::getDirections()),
            'protocol' => 'sometimes|in:' . implode(',', FirewallRule::getProtocols()),
            'port' => 'nullable|string|max:50|regex:/^[\d,:]+$/',
            'source_ip' => 'nullable|string|max:50',
            'destination_ip' => 'nullable|string|max:50',
            'comment' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'priority' => 'sometimes|integer|min:1|max:1000',
        ];
    }
}
