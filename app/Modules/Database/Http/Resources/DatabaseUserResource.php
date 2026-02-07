<?php

declare(strict_types=1);

namespace App\Modules\Database\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DatabaseUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'original_username' => $this->original_username,
            'host' => $this->host,
            'full_username' => $this->full_username,
            'privileges' => $this->privileges,
            'notes' => $this->notes,
            'databases' => $this->whenLoaded('databases', fn() =>
                $this->databases->map(fn($db) => [
                    'id' => $db->id,
                    'name' => $db->name,
                    'original_name' => $db->original_name,
                    'privileges' => $this->decodePivotPrivileges($db->pivot->privileges ?? null),
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
