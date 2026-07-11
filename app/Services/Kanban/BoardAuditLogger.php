<?php

declare(strict_types=1);

namespace App\Services\Kanban;

use App\Models\KanbanBoard;
use App\Models\KanbanBoardAuditLog;
use Database\Factories\KanbanBoardAuditLogFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Single chokepoint for writing `board_audit_logs` rows.
 *
 * Centralising the insert here keeps the audit-row schema in ONE place
 * (call sites pass `action` + `payload`, never raw column lists) and lets
 * us add cross-cutting concerns (rate limits, sampling, structured
 * logging) without touching every controller method that mutates a board.
 *
 * Action naming convention: snake_case, present-tense verb in third person
 * ("created", "archived", "renamed"). The full list lives in
 * {@see KanbanBoardAuditLogFactory::ACTIONS}.
 */
final class BoardAuditLogger
{
    /**
     * Record a single audit-log row for the given board.
     *
     * @param  KanbanBoard|Model  $board  Must expose `id`; the cast to
     *                                    `KanbanBoard` keeps callers honest
     *                                    but is loosened via `Model` for
     *                                    test fakes that subclass Eloquent.
     * @param  array<string, mixed>  $payload  Event-specific metadata. Shape
     *                                         is documented per action.
     */
    public function record(KanbanBoard $board, string $action, array $payload = []): KanbanBoardAuditLog
    {
        return KanbanBoardAuditLog::query()->create([
            'board_id' => $board->getKey(),
            // Nullable: cron / system events fall back to null.
            'actor_user_id' => auth()->id(),
            'action' => $action,
            'payload' => $payload,
        ]);
    }
}
