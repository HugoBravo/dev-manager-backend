<?php

declare(strict_types=1);

namespace App\Http\Resources\Kanban;

use App\Models\KanbanLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Label shape (sdd/kanban/labels brief).
 *
 * Mirrors the envelope used by BoardResource / ColumnResource / CardResource:
 * a single-resource response is `{ "data": { ... } }`, a list response is
 * a paginated `{ data: [...], links: {...}, meta: {...} }`.
 *
 * @mixin KanbanLabel
 */
final class KanbanLabelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'id' => $this->resource->id,
                'name' => $this->resource->name,
                'color' => $this->resource->color,
                'created_at' => optional($this->resource->created_at)?->toIso8601String(),
                'updated_at' => optional($this->resource->updated_at)?->toIso8601String(),
            ],
        ];
    }
}
