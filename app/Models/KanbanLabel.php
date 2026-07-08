<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\KanbanLabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * KanbanLabel — a user-owned color tag that can be applied to any number
 * of cards across the user's projects. Labels are global to a user
 * (NOT scoped to a project): a single user has one "bug" label that
 * they can put on cards in any of their projects.
 *
 * `color` is enforced to be a 7-char `#RRGGBB` hex string by the
 * FormRequest layer (StoreKanbanLabelRequest / UpdateKanbanLabelRequest).
 * The migration column is wider so a future change can extend the format
 * (e.g. `#RRGGBBAA`) without a destructive migration.
 *
 * @mixin Builder
 */
#[Fillable(['user_id', 'name', 'color'])]
class KanbanLabel extends Model
{
    /** @use HasFactory<KanbanLabelFactory> */
    use HasFactory;

    protected $table = 'kanban_labels';

    /**
     * The owning user. A label is invisible to other users — controllers
     * filter by `user_id = auth()->id()` everywhere.
     *
     * @return BelongsTo<User, self>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Cards that carry this label. Inverse of `KanbanCard::labels()`.
     *
     * @return BelongsToMany<KanbanCard>
     */
    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(KanbanCard::class, 'kanban_card_label', 'label_id', 'card_id')
            ->withTimestamps();
    }
}
