<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\KanbanAttachment;
use App\Models\KanbanBoard;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Models\KanbanComment;
use App\Models\Project;
use App\Models\Secret;
use App\Policies\KanbanAttachmentPolicy;
use App\Policies\KanbanBoardPolicy;
use App\Policies\KanbanCardPolicy;
use App\Policies\KanbanColumnPolicy;
use App\Policies\KanbanCommentPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SecretPolicy;
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
        Gate::policy(KanbanBoard::class, KanbanBoardPolicy::class);
        Gate::policy(KanbanColumn::class, KanbanColumnPolicy::class);
        Gate::policy(KanbanCard::class, KanbanCardPolicy::class);
        Gate::policy(KanbanComment::class, KanbanCommentPolicy::class);
        Gate::policy(KanbanAttachment::class, KanbanAttachmentPolicy::class);
        Gate::policy(Secret::class, SecretPolicy::class);

        // Ownership-scoped route binding for `{project}`. URI segments may
        // arrive as either a numeric id (legacy callers / tests) or a slug
        // (shareable URLs from the client). The closure resolves by id when
        // the value is numeric and by slug otherwise, then scopes by
        // owner_id so cross-owner access renders as 404 (ModelNotFoundException)
        // — matching the 404-not-403 contract documented in design §7.
        // Archived_at is intentionally NOT checked here; the controller layer
        // (KanbanRequestScope trait) owns that filter.
        Route::bind('project', function (string $value): Project {
            $userId = request()->user()?->id;
            if ($userId === null) {
                throw (new ModelNotFoundException)->setModel(Project::class, [$value]);
            }

            $query = Project::query()->where('owner_id', $userId);

            if (is_numeric($value)) {
                $query->whereKey((int) $value);
            } else {
                $query->where('slug', $value);
            }

            $project = $query->first();

            if ($project === null) {
                throw (new ModelNotFoundException)->setModel(Project::class, [$value]);
            }

            return $project;
        });

        // Owner-scoped chokepoint (Batch 7 PHPDoc note):
        //
        // Every `Route::bind(...)` closure below walks the ownership chain
        // (project -> owner_id) and returns 404 (ModelNotFoundException) for
        // cross-owner access. This is the FIRST line of defense for the
        // 404-not-403 contract documented in design §7. The closures are
        // ownership-only — they do NOT consider `Project.archived_at`.
        //
        // Why archived_at is NOT in the closures:
        //   The binding closures are scoped to a SINGLE concern (ownership).
        //   Adding `archived_at` would conflate ownership with lifecycle state
        //   and force the closure to read the `?include_archived=1` query
        //   string — coupling two unrelated request attributes to the binding.
        //   Instead, archived_at is honored at the controller layer via the
        //   `KanbanRequestScope` trait (see App\Http\Controllers\Api\V1\
        //   Kanban\Concerns\KanbanRequestScope). The trait's `ensureNotArchivedProject`
        //   helper checks `config('kanban.include_archived_default')` and the
        //   request's `?include_archived=1` flag, then throws ModelNotFoundException
        //   (404) when the project is archived and the request did not opt in.
        //
        // Future contributors: DO NOT add archived_at checks inside these
        // closures. Keep them single-purpose (ownership scoping only). The
        // archived_at filter belongs at the controller, where the resolved
        // Project model is available alongside the Request.
        //
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
        //
        // The default binding uses SoftDeletes' global scope (filters trashed
        // out), so soft-deleted boards 404 at bind time. The restore endpoint
        // (Batch 1.4) uses a separate `{boardId}` binding that DOES include
        // trashed rows via `withTrashed()`, registered below.
        Route::bind('board', function (string $value): KanbanBoard {
            $userId = request()->user()?->id;
            if ($userId === null) {
                throw (new ModelNotFoundException)->setModel(KanbanBoard::class, [$value]);
            }

            $board = KanbanBoard::query()
                ->whereHas('project', function ($q) use ($userId): void {
                    $q->where('owner_id', $userId);
                })
                ->whereKey($value)
                ->first();

            if ($board === null) {
                throw (new ModelNotFoundException)->setModel(KanbanBoard::class, [$value]);
            }

            return $board;
        });

        // Trashed-aware variant of the `{board}` binding for the restore
        // endpoint (`POST /boards/{boardId}/restore`). Resolves a soft-deleted
        // board id within the ownership chain. The `boardId` parameter NAME
        // is distinct so Laravel routes are matched against `boardId` rather
        // than `board`, keeping the default binding unchanged.
        Route::bind('boardId', function (string $value): KanbanBoard {
            $userId = request()->user()?->id;
            if ($userId === null) {
                throw (new ModelNotFoundException)->setModel(KanbanBoard::class, [$value]);
            }

            $board = KanbanBoard::query()
                ->withTrashed()
                ->whereHas('project', function ($q) use ($userId): void {
                    $q->where('owner_id', $userId);
                })
                ->whereKey($value)
                ->first();

            if ($board === null) {
                throw (new ModelNotFoundException)->setModel(KanbanBoard::class, [$value]);
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

        // Ownership-scoped route binding for `{card}` (Batch 4). The closure
        // walks column -> board -> project -> owner — one level deeper than
        // {column} because every card is reached via
        // /projects/{project}/boards/{board}/columns/{column}/cards/{card}.
        // A card whose column belongs to a stranger's project chain resolves
        // to 404 here; the controller's `ensureCardBelongsToColumn` provides
        // a second check (URL consistency, not ownership).
        Route::bind('card', function (string $value): KanbanCard {
            $userId = request()->user()?->id;
            if ($userId === null) {
                throw (new ModelNotFoundException)->setModel(KanbanCard::class, [$value]);
            }

            $card = KanbanCard::query()
                ->whereHas('column.board.project', function ($q) use ($userId): void {
                    $q->where('owner_id', $userId);
                })
                ->whereKey($value)
                ->first();

            if ($card === null) {
                throw (new ModelNotFoundException)->setModel(KanbanCard::class, [$value]);
            }

            return $card;
        });

        // Ownership-scoped route binding for `{comment}` (Batch 5). Walks
        // card -> column -> board -> project -> owner — the deepest chain
        // in the kanban URLs. Cross-owner returns 404; controller's
        // `comment.card_id !== $card->id` check covers cross-card 404s.
        Route::bind('comment', function (string $value): KanbanComment {
            $userId = request()->user()?->id;
            if ($userId === null) {
                throw (new ModelNotFoundException)->setModel(KanbanComment::class, [$value]);
            }

            $comment = KanbanComment::query()
                ->whereHas('card.column.board.project', function ($q) use ($userId): void {
                    $q->where('owner_id', $userId);
                })
                ->whereKey($value)
                ->first();

            if ($comment === null) {
                throw (new ModelNotFoundException)->setModel(KanbanComment::class, [$value]);
            }

            return $comment;
        });

        // Ownership-scoped route binding for `{attachment}` (Batch 6).
        // Walks card -> column -> board -> project -> owner — same depth
        // as `{comment}`. Cross-owner returns 404; controller's
        // `attachment.card_id !== $card->id` covers cross-card 404s.
        Route::bind('attachment', function (string $value): KanbanAttachment {
            $userId = request()->user()?->id;
            if ($userId === null) {
                throw (new ModelNotFoundException)->setModel(KanbanAttachment::class, [$value]);
            }

            $attachment = KanbanAttachment::query()
                ->whereHas('card.column.board.project', function ($q) use ($userId): void {
                    $q->where('owner_id', $userId);
                })
                ->whereKey($value)
                ->first();

            if ($attachment === null) {
                throw (new ModelNotFoundException)->setModel(KanbanAttachment::class, [$value]);
            }

            return $attachment;
        });

        // Ownership-scoped route binding for `{secret}`. Walks
        // project -> owner — secret only resolves if the secret's project
        // belongs to the authenticated user. Cross-owner resolves to 404
        // matching the 404-not-403 contract documented in design §7.
        Route::bind('secret', function (string $value): Secret {
            $userId = request()->user()?->id;
            if ($userId === null) {
                throw (new ModelNotFoundException)->setModel(Secret::class, [$value]);
            }

            $secret = Secret::query()
                ->whereHas('project', function ($q) use ($userId): void {
                    $q->where('owner_id', $userId);
                })
                ->whereKey($value)
                ->first();

            if ($secret === null) {
                throw (new ModelNotFoundException)->setModel(Secret::class, [$value]);
            }

            return $secret;
        });
    }
}
