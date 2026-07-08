<?php

declare(strict_types=1);

namespace App\Http\Resources\Kanban;

use App\Models\KanbanCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Card shape (sdd/kanban/design §3 / Batch 4 brief).
 *
 * `data` envelope matches ColumnResource / BoardResource. Markdown body is
 * stored as a raw string — never HTML-encoded or sanitized at this layer
 * (front-end renders Markdown). `body === null` for cards without a body;
 * empty string is preserved (`body: ""`) when the user clears text.
 *
 * @mixin KanbanCard
 */
final class CardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'id' => $this->resource->id,
                'column_id' => $this->resource->column_id,
                'title' => $this->resource->title,
                // Raw Markdown body — null when unset, '' when cleared.
                'body' => $this->resource->body,
                'position' => $this->resource->position,
                'due_date' => $this->resource->due_date?->toDateString(),
                'archived_at' => optional($this->resource->archived_at)?->toIso8601String(),
                // Labels are eager-loaded by CardController via
                // `with('labels:id,name,color')`. The empty-array default
                // keeps the response shape stable when the relation is
                // not preloaded (e.g. in unit tests that hit the resource
                // directly without a controller).
                'labels' => $this->resource->relationLoaded('labels')
                    ? $this->resource->labels
                        ->map(fn ($label): array => [
                            'id' => $label->id,
                            'name' => $label->name,
                            'color' => $label->color,
                        ])
                        ->all()
                    : [],
                'created_at' => optional($this->resource->created_at)?->toIso8601String(),
                'updated_at' => optional($this->resource->updated_at)?->toIso8601String(),
            ],
        ];
    }
}
