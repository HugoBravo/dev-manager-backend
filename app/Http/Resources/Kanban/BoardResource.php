<?php

declare(strict_types=1);

namespace App\Http\Resources\Kanban;

use App\Models\KanbanBoard;
use App\Models\Task;
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
        // The embedded `task` object is intentionally minimal: just the
        // fields the frontend lists/headers need (id, name, slug, status,
        // archived_at). Heavy fields (description, timestamps) are dropped
        // to keep the board index payload small. If the frontend needs
        // them, it can fetch the task directly via /tasks/{id}.
        $taskPayload = null;
        if ($this->resource->relationLoaded('task') && $this->resource->task instanceof Task) {
            $taskPayload = [
                'id' => $this->resource->task->id,
                'name' => $this->resource->task->name,
                'slug' => $this->resource->task->slug,
                'status' => $this->resource->task->status,
                'archived_at' => optional($this->resource->task->archived_at)?->toIso8601String(),
            ];
        } elseif ($this->resource->task_id !== null) {
            // Lazy fallback when the relation was not eager-loaded:
            // serialize the embedded object from the FK alone. We avoid a
            // DB hit on the resource hot-path by relying on
            // `KanbanBoard::with('task')` callers (the index endpoint does
            // it explicitly).
            $taskPayload = [
                'id' => (int) $this->resource->task_id,
                'name' => null,
                'slug' => null,
                'status' => null,
                'archived_at' => null,
            ];
        }

        return [
            'data' => [
                'id' => $this->resource->id,
                'task_id' => $this->resource->task_id,
                'task' => $taskPayload,
                'name' => $this->resource->name,
                'position' => $this->resource->position,
                'archived_at' => optional($this->resource->archived_at)?->toIso8601String(),
                'created_at' => optional($this->resource->created_at)?->toIso8601String(),
                'updated_at' => optional($this->resource->updated_at)?->toIso8601String(),
            ],
        ];
    }
}
