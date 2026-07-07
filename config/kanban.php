<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Kanban configuration
|--------------------------------------------------------------------------
|
| Runtime tunables for the kanban capability. Centralized here so that
| the value object layer (`App\Support\Kanban\Position`) AND the
| authorization layer (`CommentPolicy` + `UpdateCommentRequest` window
| check) read from a single source of truth.
|
*/

return [
    /*
    | Window (in minutes) during which a comment author may edit or delete
    | a comment. After this window the comment is frozen — only the
    | author may still mutate it, but the time check rejects the request.
    |
    | Default 15 minutes (matches the spec). Override via the
    | KANBAN_COMMENT_EDIT_WINDOW_MINUTES env var without code changes.
    */
    'comment_edit_window_minutes' => (int) env('KANBAN_COMMENT_EDIT_WINDOW_MINUTES', 15),

    /*
    | Default behaviour for `?include_archived=1` on nested resources.
    | When `false`, archived projects (where `projects.archived_at` is
    | non-null) filter their boards/columns/cards/comments/attachments
    | out of index endpoints and 404 on show endpoints. Override via the
    | KANBAN_INCLUDE_ARCHIVED_DEFAULT env var.
    |
    | This flag is a single source of truth for the helper at
    | App\Http\Controllers\Api\V1\Concerns\KanbanRequestScope::
    | includeArchived(). Controllers MUST call that helper, never
    | `request()->boolean('include_archived')`, so the contract stays
    | consistent across the chain.
    */
    'include_archived_default' => (bool) env('KANBAN_INCLUDE_ARCHIVED_DEFAULT', false),
];
