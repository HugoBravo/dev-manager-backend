<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Board;
use App\Models\User;

/**
 * Board authorization — delegates to ProjectPolicy (the ownership chokepoint).
 * Every method routes through `$user->can('view', $board->project)`.
 *
 * Cross-owner resource leak avoidance (404-not-403) is handled at route binding
 * time by the `Route::bind('board', ...)` closure in AppServiceProvider::boot().
 */
final class BoardPolicy
{
    /**
     * Any authenticated user may create a board — actual ownership is decided
     * by the parent project's owner_id (enforced via the route prefix).
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Board $board): bool
    {
        return $user->can('view', $board->project);
    }

    public function update(User $user, Board $board): bool
    {
        return $user->can('view', $board->project);
    }

    /**
     * A board may be deleted only if empty (no columns AND no cards under it).
     * In Batch 2 only boards with no relationship to `columns` exist; the
     * "non-empty" check is implemented by the controller as a 409 with a
     * typed `BoardHasContentsException` — see BoardController::destroy.
     */
    public function delete(User $user, Board $board): bool
    {
        return $user->can('view', $board->project);
    }

    /**
     * Archiving requires the same ownership as view.
     */
    public function archive(User $user, Board $board): bool
    {
        return $user->can('view', $board->project);
    }

    /**
     * Reordering requires the same ownership as view.
     */
    public function reorder(User $user, Board $board): bool
    {
        return $user->can('view', $board->project);
    }
}
