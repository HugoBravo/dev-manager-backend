<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KanbanBoard;
use App\Models\User;

/**
 * KanbanBoard authorization — delegates to ProjectPolicy (the ownership chokepoint).
 * Every method routes through `$user->can('view', $board->task->project)`.
 *
 * After the kanban-per-task refactor, the board's owning project is
 * reachable only via the `task` relationship (the `kanban_boards`
 * table no longer carries `project_id`). The lookup still terminates at
 * `ProjectPolicy::view` so the chokepoint stays in one place.
 *
 * Cross-owner resource leak avoidance (404-not-403) is handled at route binding
 * time by the `Route::bind('board', ...)` closure in AppServiceProvider::boot().
 */
final class KanbanBoardPolicy
{
    /**
     * Any authenticated user may create a board — actual ownership is decided
     * by the parent project's owner_id (enforced via the route prefix).
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, KanbanBoard $board): bool
    {
        return $user->can('view', $board->task->project);
    }

    public function update(User $user, KanbanBoard $board): bool
    {
        return $user->can('view', $board->task->project);
    }

    /**
     * A board may be deleted only if empty (no columns AND no cards under it).
     * In Batch 2 only boards with no relationship to `columns` exist; the
     * "non-empty" check is implemented by the controller as a 409 with a
     * typed `Kanban\BoardHasContentsException` — see Kanban\BoardController::destroy.
     */
    public function delete(User $user, KanbanBoard $board): bool
    {
        return $user->can('view', $board->task->project);
    }

    /**
     * Archiving requires the same ownership as view.
     */
    public function archive(User $user, KanbanBoard $board): bool
    {
        return $user->can('view', $board->task->project);
    }

    /**
     * Reordering requires the same ownership as view.
     */
    public function reorder(User $user, KanbanBoard $board): bool
    {
        return $user->can('view', $board->task->project);
    }

    /**
     * Restoring a soft-deleted board — same ownership as view.
     * The controller layer additionally checks that `deleted_at` IS NOT NULL
     * before invoking the restore (and throws BoardNotTrashedException->422
     * if the board is still active). The policy is the ownership chokepoint;
     * the controller is the lifecycle chokepoint.
     */
    public function restore(User $user, KanbanBoard $board): bool
    {
        return $user->can('view', $board->task->project);
    }

    /**
     * Cloning a board — same ownership as view. The controller additionally
     * rejects clones of soft-deleted boards (404) and applies the
     * (Copy)/(Copy N) suffix convention for the resulting name.
     */
    public function clone(User $user, KanbanBoard $board): bool
    {
        return $user->can('view', $board->task->project);
    }

    /**
     * Listing trashed boards is a project-scoped read. We require a project
     * scope (passed via `KanbanBoardPolicy::viewTrashed` -> `ProjectPolicy::view`),
     * not a board instance, because trashed listing is invoked without a
     * specific board id.
     */
    public function viewTrashed(User $user, Project $project): bool
    {
        return $user->can('view', $project);
    }

    /**
     * Reading the board's audit trail requires the same ownership as the
     * board itself — `ProjectPolicy::view` is the chokepoint. The route
     * binding closure already 404s on cross-owner boards; this gate is
     * belt-and-braces so a future refactor that loosens the binding does
     * not silently leak the trail.
     */
    public function viewAudit(User $user, KanbanBoard $board): bool
    {
        return $user->can('view', $board->task->project);
    }
}
