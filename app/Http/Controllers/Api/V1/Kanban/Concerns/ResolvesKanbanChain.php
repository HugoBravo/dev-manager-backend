<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kanban\Concerns;

use App\Models\KanbanBoard;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Models\Project;
use App\Models\Task;
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
     * The `Route::bind('project', ...)` closure in AppServiceProvider already
     * filters by owner_id before the controller runs, so the bound instance
     * is guaranteed to belong to the authenticated user. We re-verify
     * owner_id here as belt-and-braces so the 404-not-403 contract
     * (design §7) survives any future loosening of the binding closure.
     */
    private function resolveOwnedProject(Request $request, Project $project): Project
    {
        if ($project->owner_id !== $request->user()->id) {
            throw (new ModelNotFoundException)->setModel(Project::class, [$project->getRouteKey()]);
        }

        return $project;
    }

    private function ensureBoardBelongsToProject(KanbanBoard $board, Project $project): void
    {
        $task = request()->route('task');
        if ($task instanceof Task) {
            $this->ensureBoardBelongsToTask($board, $task);

            return;
        }

        // No `{task}` in the URL chain (a legacy / pre-refactor call site).
        // After commit 8 the `project_id` column is gone, so we route
        // through the task relationship for the chain check.
        $boardTask = $board->task;
        if ($boardTask === null || $boardTask->project_id !== $project->id) {
            throw (new ModelNotFoundException)->setModel(KanbanBoard::class, [$board->id]);
        }
    }

    /**
     * Throw 404 when the board is not owned by the task in the URL chain.
     */
    private function ensureBoardBelongsToTask(KanbanBoard $board, Task $task): void
    {
        if ($board->task_id !== $task->id) {
            throw (new ModelNotFoundException)->setModel(KanbanBoard::class, [$board->id]);
        }
    }

    private function ensureColumnBelongsToBoard(KanbanColumn $column, KanbanBoard $board): void
    {
        if ($column->board_id !== $board->id) {
            throw (new ModelNotFoundException)->setModel(KanbanColumn::class, [$column->id]);
        }
    }

    private function ensureCardBelongsToColumn(KanbanCard $card, KanbanColumn $column): void
    {
        if ($card->column_id !== $column->id) {
            throw (new ModelNotFoundException)->setModel(KanbanCard::class, [$card->id]);
        }
    }
}
