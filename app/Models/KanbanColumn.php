<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A column belongs to a board and holds cards (Batch 4). Position is the
 * base-26 lexicographic fractional string managed by the `Position` value
 * object (`App\ValueObjects\Kanban\Position`).
 *
 * `cards()` is now a real HasMany — Kanban\ColumnController::destroy uses
 * `$column->cards()->exists()` for the 409 non-empty check; the prior
 * `cardsTableExists()` memoization has been retired.
 */
#[Fillable(['board_id', 'name', 'position', 'archived_at'])]
class KanbanColumn extends Model
{
    /** @use HasFactory<ColumnFactory> */
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
        return $this->belongsTo(KanbanBoard::class, 'board_id');
    }

    /**
     * Cards under this column.
     *
     * @phpstan-return HasMany<KanbanCard>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(KanbanCard::class, 'column_id');
    }
}
