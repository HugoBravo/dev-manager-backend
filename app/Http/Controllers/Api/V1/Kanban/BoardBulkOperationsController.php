<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kanban;

use App\Exceptions\Kanban\BoardAlreadyExistsException;
use App\Exceptions\Kanban\BoardHasContentsException;
use App\Http\Controllers\Api\V1\Kanban\Concerns\KanbanRequestScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kanban\BulkDeleteBoardsRequest;
use App\Http\Requests\Kanban\BulkRenameBoardsRequest;
use App\Http\Resources\Kanban\BulkOperationResultResource;
use App\Models\KanbanBoard;
use App\Models\Project;
use App\Models\Task;
use App\Services\Kanban\BoardAuditLogger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Bulk operations controller (sdd/kanban/design §6).
 *
 * Per-item status semantics:
 *   - 204 on a successful soft-delete;
 *   - 200 on a successful rename;
 *   - 409 `board_has_contents` when a board has columns/cards under it;
 *   - 422 `name_taken` when the rename collides with an active board;
 *   - 404 `not_found` for ids not visible to the caller.
 *
 * Bulk endpoints never return a single 4xx for the whole request — they
 * always 200 with a per-item result map so the frontend can render a
 * progress UI (succeeded count, failed count, reasons). The only whole-
 * request 4xx is a validation failure on the envelope (max ids, missing
 * prefix, invalid mode, etc.).
 */
final class BoardBulkOperationsController extends Controller
{
    use KanbanRequestScope;

    /**
     * POST /api/v1/projects/{project}/tasks/{task}/kanban/boards/bulk-delete
     */
    public function bulkDelete(BulkDeleteBoardsRequest $request, Project $project, Task $task): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project->getKey());

        $ids = array_map('intval', $request->validated('ids', []));
        $results = [];

        $logger = app(BoardAuditLogger::class);

        foreach ($ids as $id) {
            $board = $this->resolveBoardForCaller($projectModel, $task, $id);

            if ($board === null) {
                $results[] = [
                    'id' => $id,
                    'status' => 404,
                    'error' => ['code' => 'not_found'],
                ];

                continue;
            }

            $inspection = Gate::inspect('delete', $board);
            if ($inspection->denied()) {
                $results[] = [
                    'id' => $id,
                    'status' => 409,
                    'error' => [
                        'code' => 'board_has_contents',
                        'message' => (new BoardHasContentsException($board))->getMessage(),
                    ],
                ];

                continue;
            }

            DB::transaction(function () use ($board, $logger): void {
                $board->delete();
                $logger->record($board, 'bulk_deleted', []);
            });

            $results[] = [
                'id' => $id,
                'status' => 204,
            ];
        }

        return (new BulkOperationResultResource(['results' => $results]))
            ->response();
    }

    /**
     * POST /api/v1/projects/{project}/tasks/{task}/kanban/boards/bulk-rename
     */
    public function bulkRename(BulkRenameBoardsRequest $request, Project $project, Task $task): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project->getKey());

        $ids = array_map('intval', $request->validated('ids', []));
        $prefix = (string) $request->validated('prefix');
        $mode = (string) $request->validated('mode');

        $results = [];
        $logger = app(BoardAuditLogger::class);

        foreach ($ids as $id) {
            $board = $this->resolveBoardForCaller($projectModel, $task, $id);

            if ($board === null) {
                $results[] = [
                    'id' => $id,
                    'status' => 404,
                    'error' => ['code' => 'not_found'],
                ];

                continue;
            }

            $newName = $mode === 'add'
                ? $prefix.$board->name
                : $this->removePrefix($board->name, $prefix);

            // Even in remove mode, the name may not exceed 100 chars. We
            // accept the result either way (per the remove-no-op test) by
            // letting the FormRequest's max:100 rule gate at submission.
            $inspection = Gate::inspect('update', $board);
            if ($inspection->denied()) {
                $results[] = [
                    'id' => $id,
                    'status' => 404,
                    'error' => ['code' => 'not_found'],
                ];

                continue;
            }

            try {
                DB::transaction(function () use ($board, $newName, $logger): void {
                    $board->name = $newName;
                    $board->save();
                    $logger->record($board, 'bulk_renamed', ['name' => $newName]);
                });
            } catch (ValidationException|BoardAlreadyExistsException|UniqueConstraintViolationException $e) {
                $results[] = [
                    'id' => $id,
                    'status' => 422,
                    'error' => ['code' => 'name_taken'],
                ];

                continue;
            }

            $results[] = [
                'id' => $id,
                'status' => 200,
                'name' => $newName,
            ];
        }

        return (new BulkOperationResultResource(['results' => $results]))
            ->response();
    }

    /**
     * Resolve a board by id within the task's ownership chain. Returns
     * `null` when the id does not belong to the task or refers to a
     * soft-deleted row — callers translate `null` into a 404 result entry
     * (no existence leak).
     */
    private function resolveBoardForCaller(Project $project, Task $task, int $id): ?KanbanBoard
    {
        try {
            return KanbanBoard::query()
                ->whereKey($id)
                ->where('task_id', $task->id)
                ->whereHas('task.project', function ($q) use ($project): void {
                    $q->whereKey($project->getKey());
                })
                ->firstOrFail();
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * Strip the leading prefix from a board name. Returns the input
     * unchanged when the prefix is not present (remove-mode no-op).
     */
    private function removePrefix(string $name, string $prefix): string
    {
        if ($prefix === '') {
            return $name;
        }

        if (str_starts_with($name, $prefix)) {
            return substr($name, strlen($prefix));
        }

        return $name;
    }
}
