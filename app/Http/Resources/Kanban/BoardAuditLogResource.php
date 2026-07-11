<?php

declare(strict_types=1);

namespace App\Http\Resources\Kanban;

use App\Models\KanbanBoardAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wire shape for a single `board_audit_logs` row.
 *
 * The endpoint `GET /api/v1/projects/{project}/kanban/boards/{board}/audit`
 * uses `BoardAuditLogResource::collection()` so the response envelope
 * follows the same `data / links / meta` shape as every other paginated
 * collection on this API.
 *
 * `payload` is exposed as a plain array (Laravel applies the model's
 * `payload => array` cast at hydration time) so the frontend can index
 * `entry.payload.source_board_id` directly.
 *
 * @mixin KanbanBoardAuditLog
 */
final class BoardAuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'board_id' => $this->resource->board_id,
            'actor_user_id' => $this->resource->actor_user_id,
            'action' => $this->resource->action,
            'payload' => $this->resource->payload ?? [],
            'created_at' => optional($this->resource->created_at)?->toIso8601String(),
        ];
    }
}
