<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Board;
use App\Models\Card;
use App\Models\KanbanColumn;
use App\Models\Project;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * Shared ownership-resolution helpers for the kanban controllers.
 *
 * Each helper enforces one of:
 *   - URL consistency (project/board/column/card chain matches the route)
 *   - Ownership scoping (project belongs to the authenticated user)
 *
 * Throws `ModelNotFoundException` (404) on every mismatch — the controller
 * relies on the global handler to render JSON. This is the second line of
 * defense; the `Route::bind()` closures registered in AppServiceProvider
 * already enforce ownership for the route-bound models.
 *
 * R1 (Batch 7): this trait composes `KanbanRequestScope` so every nested
 * controller that pulls `ResolvesKanbanChain` automatically gains access
 * to `includeArchived()` and `ensureNotArchivedProject()` without
 * importing the trait separately.
 */
trait ResolvesKanbanChain
{
    use KanbanRequestScope;

    /**
     * Resolve the project owned by the authenticated user; 404 otherwise.
     */
    private function resolveOwnedProject(Request $request, int $projectId): Project
    {
        $model = Project::query()
            ->where('owner_id', $request->user()->id)
            ->whereKey($projectId)
            ->first();

        if ($model === null) {
            throw (new ModelNotFoundException)->setModel(Project::class, [$projectId]);
        }

        return $model;
    }

    private function ensureBoardBelongsToProject(Board $board, Project $project): void
    {
        if ($board->project_id !== $project->id) {
            throw (new ModelNotFoundException)->setModel(Board::class, [$board->id]);
        }
    }

    private function ensureColumnBelongsToBoard(KanbanColumn $column, Board $board): void
    {
        if ($column->board_id !== $board->id) {
            throw (new ModelNotFoundException)->setModel(KanbanColumn::class, [$column->id]);
        }
    }

    private function ensureCardBelongsToColumn(Card $card, KanbanColumn $column): void
    {
        if ($card->column_id !== $column->id) {
            throw (new ModelNotFoundException)->setModel(Card::class, [$card->id]);
        }
    }
}
