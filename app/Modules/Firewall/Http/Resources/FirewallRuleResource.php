<?php

declare(strict_types=1);

namespace App\Modules\Firewall\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FirewallRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'direction' => $this->direction,
            'protocol' => $this->protocol,
            'port' => $this->port,
            'source_ip' => $this->source_ip,
            'destination_ip' => $this->destination_ip,
            'comment' => $this->comment,
            'is_active' => $this->is_active,
            'is_essential' => $this->is_essential,
            'priority' => $this->priority,
            'display_label' => $this->getDisplayLabel(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
