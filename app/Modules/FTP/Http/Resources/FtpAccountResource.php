<?php

declare(strict_types=1);

namespace App\Modules\FTP\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FtpAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'domain_id' => $this->domain_id,
            'user_id' => $this->user_id,
            'username' => $this->username,
            'home_directory' => $this->home_directory,
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'quota_mb' => $this->quota_mb,
            'quota_usage' => $this->quota_usage,
            'bandwidth_mb' => $this->bandwidth_mb,
            'upload_bandwidth_kbps' => $this->upload_bandwidth_kbps,
            'download_bandwidth_kbps' => $this->download_bandwidth_kbps,
            'max_connections' => $this->max_connections,
            'max_connections_per_ip' => $this->max_connections_per_ip,
            'allowed_ips' => $this->allowed_ips,
            'denied_ips' => $this->denied_ips,
            'permissions' => [
                'upload' => $this->allow_upload,
                'download' => $this->allow_download,
                'mkdir' => $this->allow_mkdir,
                'delete' => $this->allow_delete,
                'rename' => $this->allow_rename,
            ],
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'last_login_ip' => $this->last_login_ip,
            'total_uploaded' => $this->formatted_uploaded,
            'total_downloaded' => $this->formatted_downloaded,
            'total_uploaded_bytes' => $this->total_uploaded_bytes,
            'total_downloaded_bytes' => $this->total_downloaded_bytes,
            'description' => $this->description,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'domain' => $this->whenLoaded('domain', function () {
                return [
                    'id' => $this->domain->id,
                    'name' => $this->domain->name,
                ];
            }),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
        ];
    }
}
