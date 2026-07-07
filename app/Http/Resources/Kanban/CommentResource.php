<?php

declare(strict_types=1);

namespace App\Http\Resources\Kanban;

use App\Models\KanbanComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * KanbanComment resource shape:
 *   { data: { id, card_id, author_id, parent_id, body, created_at, updated_at } }
 *
 * `parent_id` is null for top-level comments (the spec's thread-per-author
 * preserves cross-author replies as siblings, NOT nested children).
 *
 * `author_id` is nullable — a hard-deleted user preserves the comment
 * with author_id NULL and renders as "deleted user" in the front-end.
 *
 * @mixin KanbanComment
 */
final class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'id' => $this->resource->id,
                'card_id' => $this->resource->card_id,
                'author_id' => $this->resource->author_id,
                'parent_id' => $this->resource->parent_id,
                'body' => $this->resource->body,
                'created_at' => optional($this->resource->created_at)?->toIso8601String(),
                'updated_at' => optional($this->resource->updated_at)?->toIso8601String(),
            ],
        ];
    }
}
