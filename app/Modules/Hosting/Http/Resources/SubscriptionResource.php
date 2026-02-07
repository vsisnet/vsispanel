<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Http\Resources;

use App\Modules\Domain\Http\Resources\DomainResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'plan_id' => $this->plan_id,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'suspension_reason' => $this->suspension_reason,
            'is_active' => $this->isActive(),
            'is_suspended' => $this->isSuspended(),
            'is_expired' => $this->isExpired(),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'username' => $this->user->username,
                ];
            }),
            'plan' => $this->whenLoaded('plan', fn () => new PlanResource($this->plan)),
            'domains' => $this->whenLoaded('domains', fn () => DomainResource::collection($this->domains)),
            'domains_count' => $this->whenCounted('domains'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
