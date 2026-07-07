<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BoardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * A board groups kanban columns. Every board belongs to exactly one project,
 * which is the ownership chokepoint for BoardPolicy. Position is a base-36
 * fractional-indexing string (see sdd/kanban/design §5 — the value object
 * ships in Batch 3; in Batch 2 we only persist and reorder the strings).
 *
 * `columns()` and `cards()` relationships are NOT declared here yet — those
 * models (KanbanColumn / Card) ship in Batch 3 / 4. The 409-on-non-empty-board
 * logic in BoardController::destroy uses an explicit columns-table existence
 * check (lazy table inspection) until Batch 3 lands the relationship.
 */
#[Fillable(['project_id', 'name', 'position', 'archived_at'])]
class Board extends Model
{
    /** @use HasFactory<BoardFactory> */
    use HasFactory;

    /**
     * Casts for date columns so `archived_at` is always a Carbon instance
     * after a `fresh()` call. Required for `Carbon::equalTo` assertions in
     * the batch 2 archive idempotency test.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    /**
     * The owning project — the authorization chokepoint for this model.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Columns under this board. Forward-compatible with Batch 3 (kanban_columns
     * migration adds `board_id` FK cascadeOnDelete). The relationship is
     * declared here so Batch 3 only needs to create the migration + model;
     * the controller's destroy check uses `Schema::hasTable('kanban_columns')`
     * to short-circuit when the table is absent.
     *
     * @phpstan-return HasMany<KanbanColumn>
     */
    public function columns(): HasMany
    {
        return $this->hasMany(KanbanColumn::class);
    }

    /**
     * Whether the underlying `kanban_columns` table exists yet. Used by the
     * controller to gate 409-on-delete until Batch 3 ships. Cheap memoized
     * lookup so we don't pay schema-cache cost on every destroy.
     */
    public static function columnsTableExists(): bool
    {
        static $cached = null;

        if ($cached === null) {
            $cached = Schema::hasTable('kanban_columns');
        }

        return $cached;
    }
}
