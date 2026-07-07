<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kanban;

use App\Exceptions\Kanban\BoardHasContentsException;
use App\Http\Controllers\Api\V1\Kanban\Concerns\KanbanRequestScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kanban\ReorderBoardsRequest;
use App\Http\Requests\Kanban\StoreBoardRequest;
use App\Http\Requests\Kanban\UpdateBoardRequest;
use App\Http\Resources\Kanban\BoardResource;
use App\Models\KanbanBoard;
use App\Models\Project;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Board lifecycle controller (sdd/kanban/design §1, §3).
 *
 * Authorization pattern: cross-owner resolves to 404 via TWO chokepoints:
 *   - the {board} Route::bind closure in AppServiceProvider scopes by
 *     project -> owner_id at binding time (404 if not visible);
 *   - `resolveOwnedProject()` (mirrors Batch 1's ProjectController) handles
 *     the {project} prefix scope, so lists/cross-owner fetches 404 too.
 */
final class BoardController extends Controller
{
    use KanbanRequestScope;

    /**
     * Base fraction used to seed positions mid-alphabet so prepend/append
     * operations have headroom on either side.
     */
    private const BASE_FRACTION = 'm';

    /**
     * List boards of a project (paginated 25/page; archived boards hidden).
     * R1: when the project itself is archived, the list is empty unless the
     * caller passes `?include_archived=1`.
     */
    public function index(Request $request, int $project): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);

        // R1 gate: archived projects filter nested resources by default.
        if (! $this->includeArchived($request) && $projectModel->archived_at !== null) {
            return BoardResource::collection(
                KanbanBoard::query()->whereRaw('1 = 0')->paginate(25)
            )->response();
        }

        $boards = KanbanBoard::query()
            ->where('project_id', $projectModel->id)
            ->whereNull('archived_at')
            ->orderBy('position')
            ->paginate(25);

        return BoardResource::collection($boards)->response();
    }

    /**
     * Create a board. Position is auto-assigned as a fresh-mid fraction.
     */
    public function store(StoreBoardRequest $request, int $project): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);

        $nextPosition = $this->nextPositionForProject($projectModel->id);

        $board = KanbanBoard::query()->create([
            'project_id' => $projectModel->id,
            'name' => $request->validated('name'),
            'position' => $nextPosition,
        ]);

        return (new BoardResource($board))->response()->setStatusCode(201);
    }

    /**
     * Show one board (cross-owner -> 404 via binding closure).
     * R1: archived project returns 404 unless `?include_archived=1`.
     */
    public function show(Request $request, int $project, KanbanBoard $board): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);

        return (new BoardResource($board))->response();
    }

    /**
     * Rename a board (cross-owner -> 404 via binding closure).
     * R1: archived project returns 404 unless `?include_archived=1`.
     */
    public function update(UpdateBoardRequest $request, int $project, KanbanBoard $board): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);
        $this->authorize('update', $board);

        $board->fill($request->validated())->save();

        return (new BoardResource($board->fresh()))->response();
    }

    /**
     * Delete an EMPTY board (cross-owner -> 404 via binding closure).
     * A board with columns (and cards under them) returns 409 via
     * Kanban\BoardHasContentsException. Until Batch 3 ships the `kanban_columns`
     * table, the destroy succeeds unconditionally for non-empty boards
     * because there's nothing to count — Batch 3 lands the real check.
     * R1: archived project returns 404 unless `?include_archived=1`.
     */
    public function destroy(Request $request, int $project, KanbanBoard $board): Response
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);

        // Column existence check — meaningful only once kanban_columns table
        // exists (Batch 3). On Batch 2 we always return false here so the
        // 409 path is exercised only when something is attached.
        if (KanbanBoard::columnsTableExists()) {
            $count = (int) DB::table('kanban_columns')->where('board_id', $board->id)->count();
            if ($count > 0) {
                throw new BoardHasContentsException($board);
            }
        }

        // Belt-and-braces authorization. Use Gate::inspect so we can convert
        // a denial into the typed 409 instead of a 403. In Batch 2 the policy
        // returns true unconditionally; in Batch 3 the real "non-empty" deny
        // flows through this same channel.
        $inspection = Gate::inspect('delete', $board);
        if ($inspection->denied()) {
            throw new BoardHasContentsException($board);
        }

        $board->delete();

        return response()->noContent();
    }

    /**
     * Archive a board (sets archived_at). Idempotent: a second call keeps
     * the original timestamp. R1: archived project returns 404 unless
     * `?include_archived=1`.
     */
    public function archive(Request $request, int $project, KanbanBoard $board): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);
        $this->authorize('archive', $board);

        if ($board->archived_at === null) {
            $board->archived_at = now();
            $board->save();
        }

        return (new BoardResource($board->fresh()))->response();
    }

    /**
     * Reorder boards by id array. Persists monotonically-increasing
     * positions (`m`, `ma`, `maa`, ...) so the list stays stable on re-fetch.
     * R1: archived project returns 404 unless `?include_archived=1`.
     */
    public function reorder(ReorderBoardsRequest $request, int $project): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);

        $orderedIds = $request->orderedIds();

        DB::transaction(function () use ($orderedIds, $projectModel): void {
            foreach ($orderedIds as $index => $boardId) {
                KanbanBoard::query()
                    ->whereKey($boardId)
                    ->where('project_id', $projectModel->id)
                    ->update(['position' => $this->indexedPosition($index)]);
            }
        });

        return response()->json(['data' => ['reordered' => count($orderedIds)]]);
    }

    /**
     * Resolve the project owned by the authenticated user; 404 otherwise.
     * Mirrors the ProjectController pattern from Batch 1 (consistency).
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

    /**
     * Throw ModelNotFoundException (404) if the binding resolved a board that
     * does not belong to the project in the URL.
     */
    private function ensureBoardBelongsToProject(KanbanBoard $board, Project $project): void
    {
        if ($board->project_id !== $project->id) {
            throw (new ModelNotFoundException)->setModel(KanbanBoard::class, [$board->id]);
        }
    }

    /**
     * Compute the next position to append under a project. Picks the largest
     * existing position + 1 lex-rank increment. Naive; Batch 3 replaces with
     * the `Position` value object's `append()`.
     */
    private function nextPositionForProject(int $projectId): string
    {
        $largest = KanbanBoard::query()
            ->where('project_id', $projectId)
            ->orderByDesc('position')
            ->value('position');

        if ($largest === null) {
            return self::BASE_FRACTION;
        }

        return $largest.'a';
    }

    /**
     * Indexed position string for the Nth slot (0-based).
     */
    private function indexedPosition(int $index): string
    {
        return self::BASE_FRACTION.str_repeat('a', max(0, $index + 1));
    }
}
