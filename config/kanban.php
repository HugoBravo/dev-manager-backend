<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Kanban configuration
|--------------------------------------------------------------------------
|
| Runtime tunables for the kanban capability. Centralized here so that
    | the value object layer (`App\ValueObjects\Kanban\Position`) AND the
    | authorization layer (`KanbanCommentPolicy` + `Kanban\UpdateCommentRequest` window
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
    | App\Http\Controllers\Api\V1\Kanban\Concerns\KanbanRequestScope::
    | includeArchived(). Controllers MUST call that helper, never
    | `request()->boolean('include_archived')`, so the contract stays
    | consistent across the chain.
    */
    'include_archived_default' => (bool) env('KANBAN_INCLUDE_ARCHIVED_DEFAULT', false),

    /*
    | Hard cap on the number of ids accepted by the bulk board endpoints
    | (`POST /boards/bulk-delete`, `POST /boards/bulk-rename`). Per the
    | Batch 1.8 brief the cap is 100 ids per request — anything larger
    | returns 422 `max_100` so the caller can split the workload. Override
    | via the `KANBAN_BULK_MAX_IDS` env var without code changes.
    */
    'bulk_max_ids' => (int) env('KANBAN_BULK_MAX_IDS', 100),

    /*
    | Restore window (in days) for soft-deleted boards. After this window
    | the daily cron job (`PurgeSoftDeletedBoards`) force-deletes the row.
    | Override via the `KANBAN_PURGE_AFTER_DAYS` env var.
    */
    'purge_after_days' => (int) env('KANBAN_PURGE_AFTER_DAYS', 30),

    /*
    | Hard cap on the byte length of a position string. Mirrors the
    | `Position::MAX_LENGTH` constant so config-driven callers (the bulk
    | reorder endpoint and the purge job) can read the same source of
    | truth. Override via `KANBAN_POSITION_MAX_LENGTH`.
    */
    'position_max_length' => (int) env('KANBAN_POSITION_MAX_LENGTH', 1024),
];
