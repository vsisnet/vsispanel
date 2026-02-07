<?php

declare(strict_types=1);

namespace App\Modules\Backup\Http\Resources;

use App\Modules\Backup\Models\StorageRemote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'backup_config_id' => $this->backup_config_id,
            'display_name' => $this->display_name,
            'type' => $this->type,
            'status' => $this->status,
            'size_bytes' => $this->size_bytes,
            'size_formatted' => $this->size_formatted,
            'snapshot_id' => $this->snapshot_id,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'duration' => $this->duration,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata,
            'storage_remote_id' => $this->storage_remote_id,
            'remote_path' => $this->remote_path,
            'synced_remotes' => $this->synced_remotes ?? [],
            'synced_remotes_info' => $this->getSyncedRemotesInfo(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'is_trashed' => $this->trashed(),
            'needs_remote_sync' => $this->needsRemoteSync(),
            'backup_config' => new BackupConfigResource($this->whenLoaded('backupConfig')),
            'storage_remote' => $this->when($this->relationLoaded('storageRemote'), function () {
                return $this->storageRemote ? [
                    'id' => $this->storageRemote->id,
                    'name' => $this->storageRemote->name,
                    'display_name' => $this->storageRemote->display_name,
                    'type' => $this->storageRemote->type,
                ] : null;
            }),
        ];
    }

    /**
     * Check if this backup needs to be synced from remote storage
     * This is true when the backup is trashed (soft-deleted) but has synced remotes
     */
    protected function needsRemoteSync(): bool
    {
        if (!$this->trashed()) {
            return false;
        }

        $syncedRemotes = $this->synced_remotes ?? [];
        return !empty($syncedRemotes);
    }

    /**
     * Get synced remotes with full info
     */
    protected function getSyncedRemotesInfo(): array
    {
        $syncedRemotes = $this->synced_remotes ?? [];

        if (empty($syncedRemotes)) {
            return [];
        }

        $remotes = StorageRemote::whereIn('id', $syncedRemotes)->get();

        return $remotes->map(function ($remote) {
            return [
                'id' => $remote->id,
                'name' => $remote->name,
                'display_name' => $remote->display_name,
                'type' => $remote->type,
            ];
        })->toArray();
    }
}
