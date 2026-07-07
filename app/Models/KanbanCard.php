<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\KanbanCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A card is the atom of the kanban. Belongs to exactly one column (at a time);
 * the column carries the card through the project's board surface. `position`
 * is the base-26 lexicographic fraction string from `App\ValueObjects\Kanban\Position`.
 *
 * Markdown body is stored RAW — no server-side rendering, no HTML sanitization.
 * The Angular client renders Markdown; the server only enforces length.
 *
 * `archived_at` is a UI-level archive filter — not a soft delete. Hard delete
 * via `KanbanCard::delete()` is the lifecycle's terminal state; comments and
 * attachments (Batches 5 & 6) cascade at the DB level. No `SoftDeletes` trait
 * is added in v1 (sdd/kanban/design §9).
 */
#[Fillable(['column_id', 'title', 'body', 'position', 'due_date', 'archived_at'])]
class KanbanCard extends Model
{
    /** @use HasFactory<KanbanCardFactory> */
    use HasFactory;

    protected $table = 'kanban_cards';

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
     * @return HasMany<KanbanComment>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(KanbanComment::class, 'card_id');
    }

    /**
     * Attachments under this card. Cascade-delete at the DB layer removes
     * the rows when a card is destroyed; the FILES on disk are deleted
     * explicitly by `Kanban\CardController::destroy` via the
     * `CascadesKanbanCardFiles` trait (controller-led cascade — see the
     * `CONTROLLING CASCADE` docblock there).
     *
     * @return HasMany<KanbanAttachment>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(KanbanAttachment::class, 'card_id');
    }
}
