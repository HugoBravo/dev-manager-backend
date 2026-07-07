<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ColumnHasContentsException;
use App\Http\Controllers\Api\V1\Concerns\ComputesKanbanPositions;
use App\Http\Controllers\Api\V1\Concerns\ResolvesKanbanChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\MoveColumnRequest;
use App\Http\Requests\ReorderColumnsRequest;
use App\Http\Requests\StoreColumnRequest;
use App\Http\Requests\UpdateColumnRequest;
use App\Http\Resources\ColumnResource;
use App\Models\Board;
use App\Models\KanbanColumn;
use App\Support\Kanban\Position;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Column lifecycle controller (sdd/kanban/design §1, §3; Batch 3 brief).
 *
 * Authorization pattern: 404-not-403 cross-owner via:
 *   - `Route::bind('board', ...)` and `Route::bind('column', ...)` close
 *     scopes in AppServiceProvider (chokepoint bindings).
 *   - `resolveOwnedProject()` mirrors ProjectController & BoardController
 *     to handle the project's owner scope at the URL prefix.
 *
 * Position assignments: every store / reorder / move computes the next
 * position via `App\Support\Kanban\Position` (the fractional-indexing
 * value object shipping in Batch 3).
 *
 * R1 (Batch 7): every action respects the project-level `archived_at`
 * via the `KanbanRequestScope` helper exposed by `ResolvesKanbanChain`.
 * An archived project hides columns by default unless the caller
 * passes `?include_archived=1`.
 */
final class ColumnController extends Controller
{
    use ComputesKanbanPositions;
    use ResolvesKanbanChain;

    /**
     * List columns of a board (paginated 25/page; archived columns hidden).
     * R1: archived project → empty list unless `?include_archived=1`.
     */
    public function index(Request $request, int $project, Board $board): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);

        if (! $this->includeArchived($request) && $projectModel->archived_at !== null) {
            return ColumnResource::collection(
                KanbanColumn::query()->whereRaw('1 = 0')->paginate(25)
            )->response();
        }

        $columns = KanbanColumn::query()
            ->where('board_id', $board->id)
            ->whereNull('archived_at')
            ->orderBy('position')
            ->paginate(25);

        return ColumnResource::collection($columns)->response();
    }

    /**
     * Create a column. Position is auto-assigned via the `Position` VO's
     * `after()` factory — appending to the rightmost sibling under the
     * board. R1: archived project → 404 unless `?include_archived=1`.
     */
    public function store(StoreColumnRequest $request, int $project, Board $board): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);

        $nextPosition = $this->nextPositionForBoard($board->id);

        $column = KanbanColumn::query()->create([
            'board_id' => $board->id,
            'name' => $request->validated('name'),
            'position' => $nextPosition,
        ]);

        return (new ColumnResource($column))->response()->setStatusCode(201);
    }

    /**
     * Show one column (cross-owner -> 404 via binding closure).
     * R1: archived project → 404 unless `?include_archived=1`.
     */
    public function show(Request $request, int $project, Board $board, KanbanColumn $column): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);

        return (new ColumnResource($column))->response();
    }

    /**
     * Rename or archive a column (cross-owner -> 404 via binding closure).
     * R1: archived project → 404 unless `?include_archived=1`.
     */
    public function update(UpdateColumnRequest $request, int $project, Board $board, KanbanColumn $column): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);
        $this->authorize('update', $column);

        $column->fill($request->validated())->save();

        return (new ColumnResource($column->fresh()))->response();
    }

    /**
     * Delete an empty column (cross-owner -> 404 via binding closure).
     * A column with cards under it returns 409 via ColumnHasContentsException.
     * R1: archived project → 404 unless `?include_archived=1`.
     */
    public function destroy(Request $request, int $project, Board $board, KanbanColumn $column): Response
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);

        // Card-existence check via the real `cards()` relationship (ships
        // in Batch 4). The Batch 3 `cardsTableExists()` schema-cache memo
        // is retired; the HasMany is now authoritative.
        if ($column->cards()->exists()) {
            throw new ColumnHasContentsException($column);
        }

        // Belt-and-braces: a denial of ColumnPolicy::delete is treated as
        // "non-empty" and surfaces the typed 409 too. Tests in Batch 3
        // stub the policy with `delete() = false` to exercise this path;
        // Batch 4+ runs through the real HasMany branch above.
        $inspection = Gate::inspect('delete', $column);
        if ($inspection->denied()) {
            throw new ColumnHasContentsException($column);
        }

        $column->delete();

        return response()->noContent();
    }

    /**
     * Reorder columns within the same board. Position strings are picked
     * via a stable indexed sequence so re-fetch yields the same order.
     * R1: archived project → 404 unless `?include_archived=1`.
     */
    public function reorder(ReorderColumnsRequest $request, int $project, Board $board): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);

        $orderedIds = $request->orderedIds();

        DB::transaction(function () use ($orderedIds, $board): void {
            foreach ($orderedIds as $index => $columnId) {
                KanbanColumn::query()
                    ->whereKey($columnId)
                    ->where('board_id', $board->id)
                    ->update(['position' => $this->indexedPosition($index)]);
            }
        });

        return response()->json(['data' => ['reordered' => count($orderedIds)]]);
    }

    /**
     * Move a column to a different board. Both boards must belong to the
     * same project (enforced by checking `target.board.project_id`).
     * The column's id is preserved; only `board_id` and `position` change.
     *
     * Cross-owner target -> 404: the target lookup is owner-scoped via
     * `Board::whereHas('project', owner_id)` — same convention as the
     * `Route::bind('board', ...)` closure. A foreign board id resolves to
     * "not found" and Laravel renders 404.
     * Cross-project target -> 404: if the target board belongs to a
     * project that isn't this column's project, the move is refused with
     * 404 (NOT 422) to keep the existence-leak contract uniform.
     */
    public function move(
        MoveColumnRequest $request,
        int $project,
        Board $board,
        KanbanColumn $column,
    ): JsonResponse {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);

        $targetBoardId = $request->targetBoardId();
        if ($targetBoardId === $board->id) {
            // No-op: same board. Return current state.
            return (new ColumnResource($column))->response();
        }

        // Resolve the target board scoped to the user's ownership chain.
        // Cross-owner -> no board found -> 404.
        $targetBoard = Board::query()
            ->whereHas('project', function ($q) use ($request): void {
                $q->where('owner_id', $request->user()->id);
            })
            ->whereKey($targetBoardId)
            ->first();

        if ($targetBoard === null) {
            throw (new ModelNotFoundException)->setModel(Board::class, [$targetBoardId]);
        }

        // Same project? If not, refuse as 404 to keep the existence-leak
        // contract uniform — the move would orphan the column's cards
        // (Batch 4) under a different project_ownership chain.
        if ($targetBoard->project_id !== $board->project_id) {
            throw (new ModelNotFoundException)->setModel(Board::class, [$targetBoardId]);
        }

        $column->board_id = $targetBoard->id;
        $column->position = $this->nextPositionForBoard($targetBoard->id);
        $column->save();

        return (new ColumnResource($column->fresh()))->response();
    }

    /**
     * Compute the next position to append under a board via the
     * `Position` value object. Picks `Position::after(rightmost)` when
     * there are existing columns, else `Position::start()` (alphabet
     * midpoint 'n'). Column-specific counterpart lives on the trait.
     */
    private function nextPositionForBoard(int $boardId): string
    {
        $rightmost = KanbanColumn::query()
            ->where('board_id', $boardId)
            ->orderByDesc('position')
            ->value('position');

        if ($rightmost === null) {
            return Position::start()->value();
        }

        return Position::after($rightmost)->value();
    }
}
