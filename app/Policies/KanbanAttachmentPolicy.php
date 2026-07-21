<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KanbanAttachment;
use App\Models\User;

/**
 * KanbanAttachment authorization — view / update / delete ALL delegate to
 * `ProjectPolicy` via the kanban chain (attachment -> card -> column ->
 * board -> project -> owner). There is NO 403 EXCEPTION in this policy:
 * attachments have no author-based edit semantics — either you can see
 * the project's resources or you cannot.
 *
 * The 404-not-403 ownership contract is enforced by
 * `Route::bind('attachment', ...)` in AppServiceProvider::boot(); the
 * controller-side `ensureCardBelongsToColumn` provides a second check.
 *
 * Compared to `KanbanCommentPolicy`: comments have an author-vs-author 403 path
 * (Batch 5 documented exception) because there is "the author of the
 * comment" concept. Attachments have no such notion — every attachment
 * belongs to the project, not a user.
 */
final class KanbanAttachmentPolicy
{
    /**
     * Any authenticated user may create an attachment — the chokepoint
     * chain (`Route::bind('card', ...)` and the upload URL's nested
     * project/board/column/card ids) enforces ownership upstream.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * View — chain to the project owner.
     */
    public function view(User $user, KanbanAttachment $attachment): bool
    {
        return $user->can('view', $attachment->card->column->board->task->project);
    }

    /**
     * Update — chain to the project owner. (Reserved for future
     * `PATCH /attachments/{id}` — out of scope for this batch; the
     * binding closure makes cross-owner return 404.)
     */
    public function update(User $user, KanbanAttachment $attachment): bool
    {
        return $user->can('view', $attachment->card->column->board->task->project);
    }

    /**
     * Delete — chain to the project owner. The 404-not-403 contract is
     * the same as for `view`: a stranger cannot tell whether the
     * attachment exists.
     */
    public function delete(User $user, KanbanAttachment $attachment): bool
    {
        return $user->can('view', $attachment->card->column->board->task->project);
    }
}
