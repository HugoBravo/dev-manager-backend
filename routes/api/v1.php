<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BoardController;
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
| ...)` scoping closure registered in AppServiceProvider::boot(). See
| sdd/kanban/design §4 and §7.
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
        });
    });
