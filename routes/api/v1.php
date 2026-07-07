<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\BoardController;
use App\Http\Controllers\Api\V1\CardArchiveController;
use App\Http\Controllers\Api\V1\CardController;
use App\Http\Controllers\Api\V1\CardMoveController;
use App\Http\Controllers\Api\V1\ColumnController;
use App\Http\Controllers\Api\V1\CommentController;
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
    });
