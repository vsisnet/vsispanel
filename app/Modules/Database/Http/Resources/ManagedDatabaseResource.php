<?php

declare(strict_types=1);

namespace App\Modules\Database\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManagedDatabaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'original_name' => $this->original_name,
            'size_bytes' => $this->size_bytes,
            'size_formatted' => $this->size_formatted,
            'charset' => $this->charset,
            'collation' => $this->collation,
            'status' => $this->status,
            'notes' => $this->notes,
            'domain' => $this->whenLoaded('domain', fn() => [
                'id' => $this->domain->id,
                'name' => $this->domain->name,
            ]),
            'users' => $this->whenLoaded('databaseUsers', fn() =>
                $this->databaseUsers->map(fn($user) => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'original_username' => $user->original_username,
                    'host' => $user->host,
                    'privileges' => $this->decodePivotPrivileges($user->pivot->privileges ?? null),
                ])
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Decode pivot privileges from JSON string to array.
     */
    protected function decodePivotPrivileges(mixed $privileges): array
    {
        if (is_array($privileges)) {
            return $privileges;
        }

        if (is_string($privileges)) {
            return json_decode($privileges, true) ?? [];
        }

        return [];
    }
}
