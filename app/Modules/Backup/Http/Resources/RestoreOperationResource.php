<?php

declare(strict_types=1);

namespace App\Modules\Backup\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestoreOperationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'backup_id' => $this->backup_id,
            'status' => $this->status,
            'target_path' => $this->target_path,
            'include_paths' => $this->include_paths,
            'include_paths_count' => count($this->include_paths ?? []),
            'files_restored' => $this->files_restored,
            'bytes_restored' => $this->bytes_restored,
            'bytes_restored_formatted' => $this->bytes_restored_formatted,
            'output' => $this->output,
            'error_message' => $this->error_message,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'backup' => $this->whenLoaded('backup', fn () => [
                'id' => $this->backup->id,
                'snapshot_id' => $this->backup->snapshot_id,
                'type' => $this->backup->type,
                'completed_at' => $this->backup->completed_at?->toISOString(),
                'size_formatted' => $this->backup->size_formatted,
            ]),
        ];
    }
}
