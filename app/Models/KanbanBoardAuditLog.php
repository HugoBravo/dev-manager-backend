<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\KanbanBoardAuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail row for board lifecycle events.
 *
 * `payload` is a free-form JSON blob whose shape is documented per `action`
 * (see BoardAuditLogger's call sites). New event types may extend the schema
 * by adding new fields; existing action values must remain stable so the
 * frontend's audit panel can branch on them safely.
 *
 * No `updated_at` — audit rows are immutable. The migration declares only
 * `created_at`, and Eloquent's `$timestamps = false` keeps it that way.
 *
 * @mixin Builder
 */
#[Fillable(['board_id', 'actor_user_id', 'action', 'payload'])]
class KanbanBoardAuditLog extends Model
{
    /** @use HasFactory<KanbanBoardAuditLogFactory> */
    use HasFactory;

    protected $table = 'board_audit_logs';

    /**
     * Audit rows are append-only; suppress `updated_at` so a stray `save()`
     * can't mutate the row after insert.
     */
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The board that triggered the event.
     *
     * @return BelongsTo<KanbanBoard, self>
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(KanbanBoard::class, 'board_id');
    }

    /**
     * The acting user. Nullable: cron jobs (e.g. PurgeSoftDeletedBoards)
     * record rows with no actor.
     *
     * @return BelongsTo<User, self>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
