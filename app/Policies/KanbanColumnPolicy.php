<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KanbanColumn;
use App\Models\User;

/**
 * KanbanColumn authorization — delegates to ProjectPolicy (the ownership
 * chokepoint). Cross-owner resource leak avoidance (404-not-403) is
 * handled at route binding time by the `Route::bind('column', ...)`
 * closure in AppServiceProvider::boot() — mirroring the board-level
 * pattern.
 */
final class KanbanColumnPolicy
{
    /**
     * Any authenticated user may create a column — ownership is decided by
     * the parent board's project ownership (enforced upstream of this
     * policy method by the chokepoint chain).
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, KanbanColumn $column): bool
    {
        return $user->can('view', $column->board->project);
    }

    public function update(User $user, KanbanColumn $column): bool
    {
        return $user->can('view', $column->board->project);
    }

    /**
     * A column may be deleted only if empty (no cards under it). The
     * "non-empty" check is implemented by the controller as a 409 with
     * `Kanban\ColumnHasContentsException` when `cards` exists — this policy
     * returns true unconditionally and lets the controller's
     * `cardsTableExists()` branch surface the 409.
     */
    public function delete(User $user, KanbanColumn $column): bool
    {
        return $user->can('view', $column->board->project);
    }

    /**
     * Reordering requires the same ownership as view.
     */
    public function reorder(User $user, KanbanColumn $column): bool
    {
        return $user->can('view', $column->board->project);
    }

    /**
     * Move (cross-board) requires the same ownership as view on BOTH the
     * source column and target board. The controller's binding closure
     * enforces the target-board scoping separately.
     */
    public function move(User $user, KanbanColumn $column): bool
    {
        return $user->can('view', $column->board->project);
    }
}
