<?php

declare(strict_types=1);

namespace App\Modules\Task\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'type_label' => $this->type_label,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'progress' => $this->progress,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'input_data' => $this->input_data,
            'output' => $this->output,
            'error_message' => $this->error_message,
            'duration' => $this->duration,
            'duration_formatted' => $this->duration_formatted,
            'can_cancel' => $this->canBeCancelled(),
            'is_active' => $this->isActive(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'metadata' => $this->metadata,
        ];
    }
}
