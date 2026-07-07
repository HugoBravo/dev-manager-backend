<?php

declare(strict_types=1);

namespace App\Http\Resources\Kanban;

use App\Models\KanbanBoard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KanbanBoard
 */
final class BoardResource extends JsonResource
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
                'position' => $this->resource->position,
                'archived_at' => optional($this->resource->archived_at)?->toIso8601String(),
                'created_at' => optional($this->resource->created_at)?->toIso8601String(),
                'updated_at' => optional($this->resource->updated_at)?->toIso8601String(),
            ],
        ];
    }
}
