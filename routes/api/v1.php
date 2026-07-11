<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Kanban\AttachmentController;
use App\Http\Controllers\Api\V1\Kanban\BoardBulkOperationsController;
use App\Http\Controllers\Api\V1\Kanban\BoardController;
use App\Http\Controllers\Api\V1\Kanban\CardArchiveController;
use App\Http\Controllers\Api\V1\Kanban\CardController;
use App\Http\Controllers\Api\V1\Kanban\CardLabelController;
use App\Http\Controllers\Api\V1\Kanban\CardMoveController;
use App\Http\Controllers\Api\V1\Kanban\ColumnController;
use App\Http\Controllers\Api\V1\Kanban\CommentController;
use App\Http\Controllers\Api\V1\Kanban\KanbanLabelController;
use App\Http\Controllers\Api\V1\ProjectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 routes (kanban capability)
|--------------------------------------------------------------------------
|
| All routes here are mounted under /api/v1 by routes/api.php. Every route
| requires a Sanctum bearer token and is throttled by the `api` middleware
| group (60 requests/minute per the Laravel default `throttle:api`).
|
| The kanban capability is owned by `ProjectPolicy` — cross-owner requests
| resolve to 404 via the controller's owner-scoped fetch on the project root
| (ProjectController::resolveOwnedProject) AND via the `Route::bind('board',
| ...)` AND `Route::bind('column', ...)` scoping closures registered in
| AppServiceProvider::boot(). See sdd/kanban/design §4 and §7.
|
*/

Route::middleware(['auth:sanctum', 'throttle:api'])
    ->prefix('v1')
    ->group(function (): void {
        Route::apiResource('projects', ProjectController::class);

        // Nested under /projects/{project}/kanban so the auth chain is owned
        // project first, then board. The reorder route MUST come BEFORE the
        // {board} wildcard so /reorder does not get captured as {board}.
        Route::prefix('projects/{project}/kanban')->group(function (): void {
            Route::post('boards/reorder', [BoardController::class, 'reorder'])
                ->name('api.v1.projects.kanban.boards.reorder');

            // Trash + restore endpoints (Batch 1.4). The literal `trashed`
            // MUST be registered BEFORE the {board} wildcard in apiResource,
            // otherwise Laravel would match `/boards/trashed` against the
            // `{board}` binding closure (which looks for `whereKey('trashed')`
            // and 404s). Same applies to `restore` and `{boardId}` below.
            Route::get('boards/trashed', [BoardController::class, 'trashed'])
                ->name('api.v1.projects.kanban.boards.trashed');

            Route::post('boards/{boardId}/restore', [BoardController::class, 'restore'])
                ->name('api.v1.projects.kanban.boards.restore');

            Route::apiResource('boards', BoardController::class)
                ->names([
                    'index' => 'api.v1.projects.kanban.boards.index',
                    'store' => 'api.v1.projects.kanban.boards.store',
                    'show' => 'api.v1.projects.kanban.boards.show',
                    'update' => 'api.v1.projects.kanban.boards.update',
                    'destroy' => 'api.v1.projects.kanban.boards.destroy',
                ]);

            Route::post('boards/{board}/archive', [BoardController::class, 'archive'])
                ->name('api.v1.projects.kanban.boards.archive');

            // Bulk operations (Batch 1.8). Both literal routes (`bulk-delete`
            // / `bulk-rename`) MUST be registered BEFORE the apiResource
            // `{board}` wildcard or Laravel would resolve the literal as a
            // board id and 404. They live at the apiResource's level
            // because they operate across multiple boards (no single
            // {board} binding).
            Route::post('boards/bulk-delete', [BoardBulkOperationsController::class, 'bulkDelete'])
                ->name('api.v1.projects.kanban.boards.bulk-delete');

            Route::post('boards/bulk-rename', [BoardBulkOperationsController::class, 'bulkRename'])
                ->name('api.v1.projects.kanban.boards.bulk-rename');

            // Clone endpoint (Batch 1.7). Same routing concern as trash:
            // the literal path is OK here because no namespace conflict at
            // this exact level; the source board is bound by the default
            // `{board}` closure.
            Route::post('boards/{board}/clone', [BoardController::class, 'clone'])
                ->name('api.v1.projects.kanban.boards.clone');

            // Audit log read (Batch 2.2 — verify phase fix). Registered
            // BEFORE the {board} wildcard would shadow it (no conflict at
            // this exact level, but we keep the precedent set by restore /
            // archive / clone so future refactors are safe).
            Route::get('boards/{board}/audit', [BoardController::class, 'audit'])
                ->name('api.v1.projects.kanban.boards.audit');

            // Column lifecycle. The reorder + {column}/move routes are listed
            // BEFORE the {column} wildcard so /reorder and /move do not get
            // captured by it. apiResource covers the 5 standard REST verbs.
            Route::post('boards/{board}/columns/reorder', [ColumnController::class, 'reorder'])
                ->name('api.v1.projects.kanban.boards.columns.reorder');

            Route::apiResource('boards/{board}/columns', ColumnController::class)
                ->names([
                    'index' => 'api.v1.projects.kanban.boards.columns.index',
                    'store' => 'api.v1.projects.kanban.boards.columns.store',
                    'show' => 'api.v1.projects.kanban.boards.columns.show',
                    'update' => 'api.v1.projects.kanban.boards.columns.update',
                    'destroy' => 'api.v1.projects.kanban.boards.columns.destroy',
                ]);

            Route::post('boards/{board}/columns/{column}/move', [ColumnController::class, 'move'])
                ->name('api.v1.projects.kanban.boards.columns.move');

            // Card lifecycle. The reorder route MUST come BEFORE the {card}
            // wildcard so /reorder does not get captured as {card}. apiResource
            // covers the 5 standard REST verbs; archive / restore / move are
            // explicit routes registered before the {card} wildcard.
            Route::post('boards/{board}/columns/{column}/cards/reorder', [CardController::class, 'reorder'])
                ->name('api.v1.projects.kanban.boards.columns.cards.reorder');

            Route::apiResource('boards/{board}/columns/{column}/cards', CardController::class)
                ->names([
                    'index' => 'api.v1.projects.kanban.boards.columns.cards.index',
                    'store' => 'api.v1.projects.kanban.boards.columns.cards.store',
                    'show' => 'api.v1.projects.kanban.boards.columns.cards.show',
                    'update' => 'api.v1.projects.kanban.boards.columns.cards.update',
                    'destroy' => 'api.v1.projects.kanban.boards.columns.cards.destroy',
                ]);

            Route::post('boards/{board}/columns/{column}/cards/{card}/archive', [CardArchiveController::class, 'archive'])
                ->name('api.v1.projects.kanban.boards.columns.cards.archive');

            Route::post('boards/{board}/columns/{column}/cards/{card}/restore', [CardArchiveController::class, 'restore'])
                ->name('api.v1.projects.kanban.boards.columns.cards.restore');

            Route::post('boards/{board}/columns/{column}/cards/{card}/move', [CardMoveController::class, 'move'])
                ->name('api.v1.projects.kanban.boards.columns.cards.move');

            // Card ↔ label sync (Kanban labels feature). Replaces the set of
            // labels on the card with the supplied id list. Body shape is
            // `{ "label_ids": [1, 2, 3] }`; empty array clears. Cross-owner
            // resolves to 404 via the {card} Route::bind closure.
            Route::put('boards/{board}/columns/{column}/cards/{card}/labels', [CardLabelController::class, 'sync'])
                ->name('api.v1.projects.kanban.boards.columns.cards.labels.sync');

            // Comment lifecycle (Batch 5). Thread-per-author; 15-minute edit
            // window enforced via config('kanban.comment_edit_window_minutes').
            // The {comment} wildcard route is registered LAST so the apiResource
            // verbs above resolve first.
            Route::apiResource('boards/{board}/columns/{column}/cards/{card}/comments', CommentController::class)
                ->names([
                    'index' => 'api.v1.projects.kanban.boards.columns.cards.comments.index',
                    'store' => 'api.v1.projects.kanban.boards.columns.cards.comments.store',
                    'show' => 'api.v1.projects.kanban.boards.columns.cards.comments.show',
                    'update' => 'api.v1.projects.kanban.boards.columns.cards.comments.update',
                    'destroy' => 'api.v1.projects.kanban.boards.columns.cards.comments.destroy',
                ]);

            // Attachment lifecycle (Batch 6). Multipart upload, 5 MB cap,
            // mime allowlist enforced at the FormRequest layer. NO download
            // endpoint ships in v1 (out of scope per spec). The {attachment}
            // wildcard route is registered LAST so the apiResource verbs above
            // resolve first.
            Route::apiResource('boards/{board}/columns/{column}/cards/{card}/attachments', AttachmentController::class)
                ->only(['index', 'store', 'destroy'])
                ->names([
                    'index' => 'api.v1.projects.kanban.boards.columns.cards.attachments.index',
                    'store' => 'api.v1.projects.kanban.boards.columns.cards.attachments.store',
                    'destroy' => 'api.v1.projects.kanban.boards.columns.cards.attachments.destroy',
                ]);
        });

        // Kanban labels (global per user, NOT scoped to a project). A user
        // has one set of labels and applies them to cards across all their
        // projects. Routes live OUTSIDE the `/projects/{project}/kanban/...`
        // prefix by design.
        //
        // Parameter name `{label}` (not `{kanban_label}`) so Laravel's
        // implicit binding resolves to `KanbanLabel::query()->whereKey($value)`
        // instead of looking for a `kanban_label` column.
        Route::apiResource('kanban-labels', KanbanLabelController::class)
            ->parameters(['kanban-labels' => 'label'])
            ->names([
                'index' => 'api.v1.kanban-labels.index',
                'store' => 'api.v1.kanban-labels.store',
                'show' => 'api.v1.kanban-labels.show',
                'update' => 'api.v1.kanban-labels.update',
                'destroy' => 'api.v1.kanban-labels.destroy',
            ]);
    });
