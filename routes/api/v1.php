<?php

declare(strict_types=1);

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
| resolve to 404 via the controller's owner-scoped fetch (NOT 403, to
| prevent existence leaks). See sdd/kanban/design §4 and §7.
|
*/

Route::middleware(['auth:sanctum', 'throttle:api'])
    ->prefix('v1')
    ->group(function (): void {
        Route::apiResource('projects', ProjectController::class);
    });
