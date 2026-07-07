<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * KanbanComment — a typed text comment on a card. Comments are
 * CANONICAL TEXT (not Markdown), with a 1-5000 char HTTP boundary
 * enforced by the FormRequest layer (`max:5000`).
 *
 * Thread semantics: parent_id may ONLY be set when the author is the
 * same user replying on their own root. Cross-author replies create a
 * NEW top-level root (parent_id null). The validation rule lives in
 * `Kanban\StoreCommentRequest::withValidator()` and is enforced
 * authorization-layer independently.
 *
 * `author_id` is nullable to allow preservation of comment text when
 * a user is hard-deleted (NULL author renders as "deleted user" in
 * the front-end).
 *
 * @mixin Builder
 */
#[Fillable(['card_id', 'author_id', 'parent_id', 'body'])]
class KanbanComment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    protected $table = 'kanban_comments';

    /**
     * The card this comment belongs to.
     *
     * @return BelongsTo<KanbanCard, self>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(KanbanCard::class, 'card_id');
    }

    /**
     * The author (user). Nullable — a hard-deleted user leaves the row
     * with author_id NULL.
     *
     * @return BelongsTo<User, self>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * The parent comment for thread-by-author replies. Nullable.
     *
     * @return BelongsTo<self, self>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
