<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\KanbanColumnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * A column belongs to a board and holds cards (Batch 4). Position is the
 * base-26 lexicographic fractional string managed by the `Position` value
 * object (`App\Support\Kanban\Position`).
 *
 * Cards relationship is NOT declared here yet — Batch 4 will add it. The
 * 409 non-empty-column contract in `ColumnController::destroy` uses a
 * memoised table-existence check (`cardsTableExists()`) until Batch 4
 * ships the relationship — same pattern Batch 2 used for boards.
 */
#[Fillable(['board_id', 'name', 'position', 'archived_at'])]
class KanbanColumn extends Model
{
    /** @use HasFactory<KanbanColumnFactory> */
    use HasFactory;

    /**
     * Casts for `archived_at` so it surfaces as a Carbon instance.
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
     * The owning board (Batch 3 root).
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * Whether the underlying `cards` table exists yet. Used by the column
     * controller to gate the 409-on-non-empty-delete path until Batch 4
     * ships the cards table. Mirrors `Board::columnsTableExists()` from
     * Batch 2.
     */
    public static function cardsTableExists(): bool
    {
        static $cached = null;

        if ($cached === null) {
            $cached = Schema::hasTable('cards');
        }

        return $cached;
    }
}
