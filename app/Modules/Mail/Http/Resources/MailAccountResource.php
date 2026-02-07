<?php

declare(strict_types=1);

namespace App\Modules\Mail\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MailAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mail_domain_id' => $this->mail_domain_id,
            'domain_name' => $this->mailDomain?->domain?->name,
            'email' => $this->email,
            'username' => $this->username,
            'status' => $this->status,
            'quota_mb' => $this->quota_mb,
            'quota_used_bytes' => $this->quota_used_bytes,
            'quota_used_mb' => $this->quota_used_mb,
            'quota_percent' => $this->quota_usage_percent,
            'auto_responder' => [
                'enabled' => $this->auto_responder_enabled,
                'subject' => $this->auto_responder_subject,
                'message' => $this->auto_responder_message,
                'start_at' => $this->auto_responder_start_at?->toIso8601String(),
                'end_at' => $this->auto_responder_end_at?->toIso8601String(),
                'is_active' => $this->isAutoResponderActive(),
            ],
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'last_login_ip' => $this->last_login_ip,
            'forwards' => MailForwardResource::collection($this->whenLoaded('forwards')),
            'forwards_count' => $this->whenLoaded('forwards', fn() => $this->forwards->count()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
