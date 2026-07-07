<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A card is the atom of the kanban. Belongs to exactly one column (at a time);
 * the column carries the card through the project's board surface. `position`
 * is the base-26 lexicographic fraction string from `App\Support\Kanban\Position`.
 *
 * Markdown body is stored RAW — no server-side rendering, no HTML sanitization.
 * The Angular client renders Markdown; the server only enforces length.
 *
 * `archived_at` is a UI-level archive filter — not a soft delete. Hard delete
 * via `Card::delete()` is the lifecycle's terminal state; comments and
 * attachments (Batches 5 & 6) cascade at the DB level. No `SoftDeletes` trait
 * is added in v1 (sdd/kanban/design §9).
 */
#[Fillable(['column_id', 'title', 'body', 'position', 'due_date', 'archived_at'])]
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory;

    /**
     * Casts: `due_date` as date ('Y-m-d' string from the DB → Carbon),
     * `archived_at` as datetime (used by the archive endpoint).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * The owning column (Board of the card is reached via $card->column->board).
     *
     * @return BelongsTo<KanbanColumn, self>
     */
    public function column(): BelongsTo
    {
        return $this->belongsTo(KanbanColumn::class, 'column_id');
    }

    /**
     * Comments under this card. Used by the comment cascade when a card is
     * destroyed (FK CASCADE handles DB rows; this relationship is exposed
     * for the front-end to enumerate active thread roots).
     *
     * @return HasMany<CardComment>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(CardComment::class);
    }
}
