<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kanban;

use App\Exceptions\Kanban\BoardHasContentsException;
use App\Exceptions\Kanban\BoardNotTrashedException;
use App\Exceptions\Kanban\PositionExhaustedException;
use App\Http\Controllers\Api\V1\Kanban\Concerns\KanbanRequestScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kanban\CloneBoardRequest;
use App\Http\Requests\Kanban\ReorderBoardsRequest;
use App\Http\Requests\Kanban\StoreBoardRequest;
use App\Http\Requests\Kanban\UpdateBoardRequest;
use App\Http\Resources\Kanban\BoardAuditLogResource;
use App\Http\Resources\Kanban\BoardResource;
use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use App\Models\Project;
use App\Models\Task;
use App\Services\Kanban\BoardAuditLogger;
use App\ValueObjects\Kanban\Position;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletingScope;
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
    public function index(Request $request, Project $project, Task $task): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);

        // R1 gate: archived projects filter nested resources by default.
        if (! $this->includeArchived($request) && $projectModel->archived_at !== null) {
            return BoardResource::collection(
                KanbanBoard::query()->whereRaw('1 = 0')->paginate(25)
            )->response();
        }

        $boards = KanbanBoard::query()
            ->where('task_id', $task->id)
            ->whereNull('archived_at')
            ->orderBy('position')
            ->paginate(25);

        return BoardResource::collection($boards)->response();
    }

    /**
     * Create a board. Position is auto-assigned via the `Position` VO
     * (lexorank fractional indexing). The full path runs inside a
     * `DB::transaction` with `lockForUpdate` so concurrent appends cannot
     * race on `rightmost`. On `PositionExhaustedException` the controller
     * triggers a rebalance: rewrite every board's position to a fresh
     * indexed sequence and retry the append once (Batch 1.6 brief).
     */
    public function store(StoreBoardRequest $request, Project $project, Task $task): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);

        try {
            $board = DB::transaction(function () use ($request, $projectModel, $task): KanbanBoard {
                $nextPosition = $this->nextPositionForTask($task);

                return KanbanBoard::query()->create([
                    'project_id' => $projectModel->id,
                    'task_id' => $task->id,
                    'name' => $request->validated('name'),
                    'position' => $nextPosition,
                ]);
            });
        } catch (PositionExhaustedException) {
            DB::transaction(function () use ($task): void {
                $this->rebalanceTaskPositions($task);
            });

            $board = DB::transaction(function () use ($request, $projectModel, $task): KanbanBoard {
                $nextPosition = $this->nextPositionForTask($task);

                return KanbanBoard::query()->create([
                    'project_id' => $projectModel->id,
                    'task_id' => $task->id,
                    'name' => $request->validated('name'),
                    'position' => $nextPosition,
                ]);
            });
        }

        return (new BoardResource($board))->response()->setStatusCode(201);
    }

    /**
     * Show one board (cross-owner -> 404 via binding closure).
     * R1: archived project returns 404 unless `?include_archived=1`.
     * REQ-MIGRATION-2: archived task also returns 404 unless the same flag
     * is set; the helper runs after the project-level gate so either
     * archive source is reported accurately.
     */
    public function show(Request $request, Project $project, Task $task, KanbanBoard $board): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project->getKey());
        $this->ensureNotArchivedTask($request, $task, $task->getKey());

        return (new BoardResource($board))->response();
    }

    /**
     * Rename a board (cross-owner -> 404 via binding closure).
     *
     * Spec capability `board-audit-log` (sdd/boards-kanban-crud-full/spec §5)
     * requires a `renamed` audit row whenever the name changes, carrying
     * `old_name` + `new_name` in the payload. We snapshot the previous name
     * BEFORE `save()` and use `wasChanged('name')` so a no-op PATCH (same
     * name submitted back) does not pollute the audit panel.
     *
     * R1: archived project returns 404 unless `?include_archived=1`.
     */
    public function update(UpdateBoardRequest $request, Project $project, Task $task, KanbanBoard $board): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project->getKey());
        $this->authorize('update', $board);

        $oldName = (string) $board->name;
        $board->fill($request->validated())->save();

        if ($board->wasChanged('name')) {
            app(BoardAuditLogger::class)->record($board, 'renamed', [
                'old_name' => $oldName,
                'new_name' => (string) $board->name,
            ]);
        }

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
    public function destroy(Request $request, Project $project, Task $task, KanbanBoard $board): Response
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project->getKey());

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
     * Toggle a board's `archived_at`. The endpoint is a TOGGLE — the first
     * call archives (sets `archived_at = now()`), the next call unarchives
     * (clears `archived_at = null`). The audit row records the action as
     * either `archived` or `unarchived` so the audit panel can branch on
     * intent rather than timestamp deltas.
     *
     * R1: archived project returns 404 unless `?include_archived=1`.
     */
    public function archive(Request $request, Project $project, Task $task, KanbanBoard $board): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project->getKey());
        $this->authorize('archive', $board);

        $action = $board->archived_at === null ? 'archived' : 'unarchived';
        $board->archived_at = $board->archived_at === null ? now() : null;
        $board->save();

        app(BoardAuditLogger::class)->record($board, $action, [
            'archived_at' => $board->archived_at?->toIso8601String(),
        ]);

        return (new BoardResource($board->fresh()))->response();
    }

    /**
     * Clone a board: produce a new board in the same project with the same
     * columns (and zero cards — the cards table ships in Batch 4). Name
     * defaults to "{original} (Copy)" with a "(Copy N)" suffix on collision.
     * The source board is left untouched. A trashed source returns 404 (the
     * default `{board}` binding closure filters soft-deleted rows).
     */
    public function clone(CloneBoardRequest $request, Project $project, Task $task, KanbanBoard $board): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project->getKey());
        $this->authorize('clone', $board);

        $sourceName = $board->name;
        $desiredName = trim((string) $request->input('name', ''));
        $finalName = $desiredName !== '' ? $desiredName : "{$sourceName} (Copy)";
        $finalName = $this->resolveCloneNameCollision($projectModel, $task, $finalName);

        $clone = DB::transaction(function () use ($projectModel, $board, $task, $finalName): KanbanBoard {
            $position = $this->nextPositionForTask($task);

            $new = KanbanBoard::query()->create([
                'project_id' => $projectModel->id,
                'task_id' => $task->id,
                'name' => $finalName,
                'position' => $position,
            ]);

            // Copy columns, ordered by their existing position so the
            // resulting board preserves the column flow. Cards are NOT
            // copied — Batch 4 will introduce the cards relation and the
            // clone endpoint will gain a `with_cards: true` opt-in.
            $sourceColumns = KanbanColumn::query()
                ->where('board_id', $board->id)
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            foreach ($sourceColumns as $column) {
                KanbanColumn::query()->create([
                    'board_id' => $new->id,
                    'name' => $column->name,
                    'position' => $column->position,
                    'archived_at' => null,
                ]);
            }

            return $new;
        });

        // Spec capability `board-audit-log` (sdd/boards-kanban-crud-full/spec §5
        // "Clone logs both sides"): BOTH boards receive a `cloned` row. The
        // payload on each side carries both ids so the frontend audit panel
        // can render the link without an extra request.
        $clonePayload = [
            'source_board_id' => $board->id,
            'new_board_id' => $clone->id,
            'columns_cloned' => KanbanColumn::query()->where('board_id', $clone->id)->count(),
        ];

        $logger = app(BoardAuditLogger::class);
        $logger->record($clone, 'cloned', $clonePayload);
        $logger->record($board, 'cloned', $clonePayload);

        return (new BoardResource($clone->fresh()))->response()->setStatusCode(201);
    }

    /**
     * Append "(Copy N)" until the name is unique in the project (active
     * rows only). The starting "$candidate" is tried as-is first.
     */
    private function resolveCloneNameCollision(Project $project, Task $task, string $candidate): string
    {
        $exists = fn (string $name): bool => KanbanBoard::query()
            ->where('task_id', $task->id)
            ->where('name', $name)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists($candidate)) {
            return $candidate;
        }

        // Compute the base name (strip a trailing " (Copy)" or "(Copy N)"
        // so the appended suffix does not compound from a previous copy).
        $base = preg_replace('/ \(Copy(?: \d+)?\)$/', '', $candidate) ?? $candidate;

        $suffix = 2;
        while ($suffix < 1000) {
            $next = "{$base} (Copy {$suffix})";

            if (! $exists($next)) {
                return $next;
            }
            $suffix++;
        }

        // Hard fallback after 1000 collisions — matches the spirit of the
        // unbounded-name policy without unbounded retries.
        return $candidate.' ('.now()->timestamp.')';
    }

    /**
     * Reorder boards by id array. Persists monotonically-increasing
     * positions (`m`, `ma`, `maa`, ...) so the list stays stable on re-fetch.
     * Each board records an audit row with `from_position` (the previous
     * `position` string) and `to_position` (the freshly-assigned indexed
     * string) so the audit panel can render the diff.
     *
     * R1: archived project returns 404 unless `?include_archived=1`.
     */
    public function reorder(ReorderBoardsRequest $request, Project $project, Task $task): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project->getKey());

        $orderedIds = $request->orderedIds();

        $logger = app(BoardAuditLogger::class);

        DB::transaction(function () use ($orderedIds, $task, $logger): void {
            // Read current positions BEFORE the update so the audit payload
            // can record `from_position` accurately. Limited to the ids in
            // the request to keep the query scope tight.
            $current = KanbanBoard::query()
                ->whereIn('id', $orderedIds)
                ->where('task_id', $task->id)
                ->pluck('position', 'id');

            foreach ($orderedIds as $index => $boardId) {
                $from = (string) ($current[$boardId] ?? '');
                $to = $this->indexedPosition($index);

                KanbanBoard::query()
                    ->whereKey($boardId)
                    ->where('task_id', $task->id)
                    ->update(['position' => $to]);

                $logger->record(
                    KanbanBoard::query()->whereKey($boardId)->firstOrFail(),
                    'reordered',
                    ['from_position' => $from, 'to_position' => $to],
                );
            }
        });

        return response()->json(['data' => ['reordered' => count($orderedIds)]]);
    }

    /**
     * Restore a soft-deleted board to the active list. The {board} route
     * binding closure scopes by ownership AND filters out soft-deleted rows
     * (the SoftDeletes global scope), so a trashed board id never resolves at
     * bind time — we look it up manually with the scope dropped, then verify
     * ownership + lifecycle state. A foreign or non-existent board 404s;
     * an already-active board returns 422 BoardNotTrashedException.
     * R1: archived project returns 404 unless `?include_archived=1`.
     */
    public function restore(Request $request, Project $project, Task $task, KanbanBoard $board): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project->getKey());

        // The bound instance IS the trashed board (binding closure uses
        // withTrashed so soft-deleted rows resolve). Verify ownership + state.
        $trashed = KanbanBoard::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereKey($board->getKey())
            ->where('task_id', $task->id)
            ->first();

        if ($trashed === null) {
            throw (new ModelNotFoundException)->setModel(KanbanBoard::class, [$board->getKey()]);
        }

        if ($trashed->deleted_at === null) {
            throw new BoardNotTrashedException($trashed);
        }

        DB::transaction(function () use ($trashed): void {
            $trashed->deleted_at = null;
            $trashed->save();

            app(BoardAuditLogger::class)->record($trashed, 'restored', []);
        });

        return (new BoardResource($trashed->fresh()))->response();
    }

    /**
     * List trashed boards for a project, paginated 25/page, ordered newest-first
     * (deleted_at DESC). The default index excludes trashed rows via the
     * SoftDeletes global scope — this endpoint drops it for the single query.
     * R1: archived project returns 404 unless `?include_archived=1`.
     */
    public function trashed(Request $request, Project $project, Task $task): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project->getKey());

        $boards = KanbanBoard::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('task_id', $task->id)
            ->whereNotNull('deleted_at')
            ->orderByDesc('deleted_at')
            ->paginate(25);

        return BoardResource::collection($boards)->response();
    }

    /**
     * Paginated audit log for a single board, newest first. The
     * `BoardAuditLogResource` collection preserves Laravel's standard
     * `data / links / meta` envelope so the frontend's `listBoardAudit`
     * helper can use the same pagination decoder it already trusts.
     *
     * Authorization (`viewAudit` gate) delegates to the project ownership
     * chokepoint; cross-owner requests never reach this method because the
     * `{board}` Route::bind closure throws 404 first.
     *
     * R1: archived project returns 404 unless `?include_archived=1`.
     */
    public function audit(Request $request, Project $project, Task $task, KanbanBoard $board): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project->getKey());
        $this->authorize('viewAudit', $board);

        $logs = $board->auditLogs()
            ->orderByDesc('created_at')
            ->paginate(25);

        return BoardAuditLogResource::collection($logs)->response();
    }

    /**
     * Resolve the project owned by the authenticated user; 404 otherwise.
     *
     * Moved to the `KanbanRequestScope` trait in Batch 3 so the same
     * belt-and-braces ownership check is shared with
     * `BoardBulkOperationsController` (and any future kanban controller).
     * The trait-level method now owns this helper; call sites are
     * unchanged.
     */

    /**
     * Throw ModelNotFoundException (404) if the binding resolved a board that
     * does not belong to the task in the URL. Every kanban route now carries
     * a `{task}` segment after the kanban-per-task refactor, so the
     * `task_id` check is the only chain-consistency gate we need here. The
     * `Route::bind('board', …)` closure already filters by project → owner_id,
     * and `ResolvesKanbanChain::ensureBoardBelongsToTask` covers the same
     * gate when controllers compose the trait.
     */
    private function ensureBoardBelongsToProject(KanbanBoard $board, Project $project): void
    {
        $task = request()->route('task');
        if (! $task instanceof Task) {
            // No {task} route segment — without a task there is no chain to
            // validate against, but the controller shouldn't have been called
            // in that configuration. Treat as 404 to fail loudly.
            throw (new ModelNotFoundException)->setModel(KanbanBoard::class, [$board->id]);
        }

        if ($board->task_id !== $task->id) {
            throw (new ModelNotFoundException)->setModel(KanbanBoard::class, [$board->id]);
        }
    }

    /**
     * Compute the next position to append under a task via the
     * `Position` value object. Picks the largest existing position and asks
     * `Position::after($rightmost)` for a strictly-greater value within
     * the 1024-byte cap. Returns `Position::start()` when the task has
     * no boards.
     *
     * Wrapped by the controller in `DB::transaction` with `lockForUpdate`
     * so concurrent appends cannot race on `rightmost`. Throws
     * `PositionExhaustedException` when the alphabet is saturated; the
     * controller catches that and triggers `rebalanceTaskPositions`.
     *
     * @throws PositionExhaustedException
     */
    private function nextPositionForTask(Task $task): string
    {
        $rightmost = KanbanBoard::query()
            ->where('task_id', $task->id)
            ->lockForUpdate()
            ->orderByDesc('position')
            ->value('position');

        if ($rightmost === null) {
            return Position::start()->value();
        }

        return Position::after($rightmost)->value();
    }

    /**
     * Rebalance: rewrite every board's position to a fresh indexed sequence
     * derived from the `indexedPosition()` helper. The board order is
     * preserved (read in current ordering) and the rebalance produces
     * positions small enough to leave room for many future appends.
     */
    private function rebalanceTaskPositions(Task $task): void
    {
        $boardIds = KanbanBoard::query()
            ->where('task_id', $task->id)
            ->orderBy('position')
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        foreach ($boardIds as $index => $boardId) {
            KanbanBoard::query()
                ->whereKey($boardId)
                ->where('task_id', $task->id)
                ->update(['position' => $this->indexedPosition($index)]);
        }
    }

    /**
     * Indexed position string for the Nth slot (0-based).
     */
    private function indexedPosition(int $index): string
    {
        return self::BASE_FRACTION.str_repeat('a', max(0, $index + 1));
    }
}
