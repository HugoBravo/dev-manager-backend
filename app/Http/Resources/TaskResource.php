<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
final class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'id' => $this->resource->id,
                'project_id' => $this->resource->project_id,
                'name' => $this->resource->name,
                'slug' => $this->resource->slug,
                'description' => $this->resource->description,
                'status' => $this->resource->status,
                'priority' => $this->resource->priority,
                'archived_at' => optional($this->resource->archived_at)?->toIso8601String(),
                'created_at' => optional($this->resource->created_at)?->toIso8601String(),
                'updated_at' => optional($this->resource->updated_at)?->toIso8601String(),
            ],
        ];
    }
}
