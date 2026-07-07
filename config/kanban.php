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
];
