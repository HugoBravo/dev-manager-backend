<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Board;
use App\Models\KanbanColumn;
use App\Models\Project;
use App\Policies\BoardPolicy;
use App\Policies\ColumnPolicy;
use App\Policies\ProjectPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Default `throttle:api` rate limiter — required by routes/api/v1.php.
        // Mirrors Laravel's stock default (60 req/min per user or IP) so the
        // throttle:api middleware on every v1 route resolves.
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Explicit policy mapping for Project (chokepoint policy). Laravel 13
        // auto-discovers App\Policies\*Policy for App\Models\* in most cases,
        // but binding it explicitly here documents the ownership contract and
        // future-proofs against namespace shifts.
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Board::class, BoardPolicy::class);
        Gate::policy(KanbanColumn::class, ColumnPolicy::class);

        // Ownership-scoped route binding for `{board}`. Resolves to 404
        // (ModelNotFoundException) when the board does not belong to a project
        // the authenticated user owns. This is the FIRST line of defense for
        // 404-not-403 cross-owner access — the controller's
        // `ensureBoardBelongsToProject` is belt-and-braces.
        //
        // Convention lock: this closure SCOPES by project -> owner_id, NOT
        // just by board existence. A board belonging to someone else's
        // project is invisible. Same pattern below for `{column}` —
        // Column needs board -> project -> owner, so the closure walks
        // through two levels of ownership. Batches 4-6 must follow this
        // exact pattern for `{card}`, `{comment}`, `{attachment}`.
        Route::bind('board', function (string $value): Board {
            $userId = request()->user()?->id;
            if ($userId === null) {
                throw (new ModelNotFoundException)->setModel(Board::class, [$value]);
            }

            $board = Board::query()
                ->whereHas('project', function ($q) use ($userId): void {
                    $q->where('owner_id', $userId);
                })
                ->whereKey($value)
                ->first();

            if ($board === null) {
                throw (new ModelNotFoundException)->setModel(Board::class, [$value]);
            }

            return $board;
        });

        // Ownership-scoped route binding for `{column}`. The closure walks
        // board -> project -> owner — one level deeper than {board} because
        // every column is reached via /projects/{project}/boards/{board}/columns/{column}.
        // A column whose board is owned by a stranger resolves to 404 here
        // and the controller's `ensureColumnBelongsToBoard` provides a
        // second check (URL consistency, not ownership).
        Route::bind('column', function (string $value): KanbanColumn {
            $userId = request()->user()?->id;
            if ($userId === null) {
                throw (new ModelNotFoundException)->setModel(KanbanColumn::class, [$value]);
            }

            $column = KanbanColumn::query()
                ->whereHas('board.project', function ($q) use ($userId): void {
                    $q->where('owner_id', $userId);
                })
                ->whereKey($value)
                ->first();

            if ($column === null) {
                throw (new ModelNotFoundException)->setModel(KanbanColumn::class, [$value]);
            }

            return $column;
        });
    }
}
