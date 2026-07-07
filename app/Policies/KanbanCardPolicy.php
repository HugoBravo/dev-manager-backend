<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KanbanCard;
use App\Models\User;

/**
 * KanbanCard authorization — delegates to ProjectPolicy (the ownership chokepoint).
 * Cross-owner resource-leak avoidance (404-not-403) is handled at route binding
 * time by `Route::bind('card', ...)` in AppServiceProvider::boot().
 *
 * The chain is `card -> column -> board -> project -> owner`, so each policy
 * method resolves ownership through `column.board.project`. Mirror of
 * KanbanBoardPolicy / KanbanColumnPolicy patterns.
 */
final class KanbanCardPolicy
{
    /**
     * Any authenticated user may create a card — ownership is decided by the
     * parent column's board's project ownership (enforced upstream of this
     * policy method by the chokepoint chain + Route::bind('card', ...)).
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, KanbanCard $card): bool
    {
        return $user->can('view', $card->column->board->project);
    }

    public function update(User $user, KanbanCard $card): bool
    {
        return $user->can('view', $card->column->board->project);
    }

    public function delete(User $user, KanbanCard $card): bool
    {
        return $user->can('view', $card->column->board->project);
    }

    /**
     * Archive / restore — same chokepoint path.
     */
    public function archive(User $user, KanbanCard $card): bool
    {
        return $user->can('view', $card->column->board->project);
    }

    public function restore(User $user, KanbanCard $card): bool
    {
        return $user->can('view', $card->column->board->project);
    }

    /**
     * Move (cross-column) — same chokepoint path; the controller does its own
     * owner-scoped lookup for the target column id so a cross-project target
     * returns 404 not 422 (mirror of Batch 3's `move` for columns).
     */
    public function move(User $user, KanbanCard $card): bool
    {
        return $user->can('view', $card->column->board->project);
    }

    /**
     * Reorder requires the same ownership as view; the controller scopes
     * the column id via the URL binding closure.
     */
    public function reorder(User $user, KanbanCard $card): bool
    {
        return $user->can('view', $card->column->board->project);
    }
}
