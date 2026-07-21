<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KanbanComment;
use App\Models\User;

/**
 * Comment authorization — view delegates through the kanban chain
 * (comment -> card -> column -> board -> project -> owner). The 404
 * cross-owner contract is enforced by `Route::bind('comment', ...)`
 * in AppServiceProvider::boot().
 *
 * Update / delete are the DOCUMENTED 403 EXCEPTIONS to the 404 rule:
 * within the same owned project, only the comment's author may
 * mutate it. If the project already passed ownership via the
 * binding closure, the comment IS visible — returning 404 here
 * would falsely tell a second author "this comment does not exist"
 * when they can see it on `index`. 403 is the honest answer.
 *
 * Edit window: sdd/kanban/design §4 only describes the authorization
 * shape; the time-bound check (15-minute window via
 * `config('kanban.comment_edit_window_minutes')`) lives in the
 * controller's FormRequest layer and raises `ValidationException`
 * (422) when expired — NOT here, since policy methods cannot return
 * a 422-shaped denial cleanly.
 */
final class KanbanCommentPolicy
{
    /**
     * Any authenticated user may create a comment — the chokepoint
     * chain (`Route::bind('comment', ...)` + `Route::bind('card', ...)`)
     * enforces ownership upstream.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * View — chain to the project owner via KanbanCardPolicy's project route.
     */
    public function view(User $user, KanbanComment $comment): bool
    {
        return $user->can('view', $comment->card->column->board->task->project);
    }

    /**
     * Update — only the author. Inside an owned project, a different
     * author receives 403 (AuthorizationException); a non-owner hits
     * the 404 binding closure upstream.
     */
    public function update(User $user, KanbanComment $comment): bool
    {
        return $comment->author_id === $user->id;
    }

    /**
     * Delete — same author rule. By design, project owners and admins
     * CANNOT delete other users' comments in v1 — locked decision
     * (see commit body). Add moderation tools via a separate sdd change.
     */
    public function delete(User $user, KanbanComment $comment): bool
    {
        return $comment->author_id === $user->id;
    }
}
