<?php

declare(strict_types=1);

namespace App\Http\Resources\Kanban;

use App\Models\KanbanColumn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KanbanColumn
 */
final class ColumnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'id' => $this->resource->id,
                'board_id' => $this->resource->board_id,
                'name' => $this->resource->name,
                'position' => $this->resource->position,
                'archived_at' => optional($this->resource->archived_at)?->toIso8601String(),
                'created_at' => optional($this->resource->created_at)?->toIso8601String(),
                'updated_at' => optional($this->resource->updated_at)?->toIso8601String(),
            ],
        ];
    }
}
