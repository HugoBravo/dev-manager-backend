<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kanban;

use App\Http\Controllers\Api\V1\Kanban\Concerns\CascadesKanbanCardFiles;
use App\Http\Controllers\Api\V1\Kanban\Concerns\ComputesKanbanPositions;
use App\Http\Controllers\Api\V1\Kanban\Concerns\ResolvesKanbanChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kanban\ReorderCardsRequest;
use App\Http\Requests\Kanban\StoreCardRequest;
use App\Http\Requests\Kanban\UpdateCardRequest;
use App\Http\Resources\Kanban\CardResource;
use App\Models\KanbanBoard;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Card lifecycle controller — REST CRUD + reorder.
 *
 * Split:
 *   - CardController        ← this file: index/store/show/update/destroy/reorder
 *   - CardArchiveController ← archive/restore
 *   - CardMoveController    ← cross-column move (owner-scoped)
 *
 * Authorization pattern: 404-not-403 cross-owner via `Route::bind('card', ...)`
 * scoping closure in AppServiceProvider + controller-side `resolveOwnedProject`.
 *
 * Markdown body is stored RAW — never HTML-encoded, never sanitized.
 *
 * R1 (Batch 7): every action respects the project-level `archived_at`
 * via the `KanbanRequestScope` helper exposed by `ResolvesKanbanChain`.
 * An archived project hides cards by default unless the caller passes
 * `?include_archived=1`. The card-level `?archived=1` query filter
 * (existing since Batch 4) is orthogonal — it filters the card's own
 * archive flag, not the project's.
 */
final class CardController extends Controller
{
    use CascadesKanbanCardFiles;
    use ComputesKanbanPositions;
    use ResolvesKanbanChain;

    /**
     * List cards of a column. `?archived=1` includes archived cards (default
     * hides them). Cross-owner → 404 via Route::bind.
     * R1: archived project → empty list unless `?include_archived=1`.
     */
    public function index(Request $request, int $project, KanbanBoard $board, KanbanColumn $column): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);

        if (! $this->includeArchived($request) && $projectModel->archived_at !== null) {
            return CardResource::collection(
                KanbanCard::query()->whereRaw('1 = 0')->paginate(25)
            )->response();
        }

        $includeArchived = $request->boolean('archived');

        $query = KanbanCard::query()->where('column_id', $column->id);

        if (! $includeArchived) {
            $query->whereNull('archived_at');
        }

        $cards = $query->orderBy('position')->paginate(25);

        return CardResource::collection($cards)->response();
    }

    /**
     * Create a card. Position assigned via `Position::after(rightmost)` —
     * appending to the bottom of the column.
     * R1: archived project → 404 unless `?include_archived=1`.
     */
    public function store(StoreCardRequest $request, int $project, KanbanBoard $board, KanbanColumn $column): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);

        $card = KanbanCard::query()->create([
            'column_id' => $column->id,
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
            'due_date' => $request->validated('due_date'),
            'position' => $this->nextPositionForColumn($column->id),
        ]);

        return (new CardResource($card))->response()->setStatusCode(201);
    }

    /**
     * Show one card. Cross-owner → 404 via Route::bind.
     * R1: archived project → 404 unless `?include_archived=1`.
     */
    public function show(Request $request, int $project, KanbanBoard $board, KanbanColumn $column, KanbanCard $card): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);

        return (new CardResource($card))->response();
    }

    /**
     * Update title / body / due_date on a card. Cross-owner → 404.
     * R1: archived project → 404 unless `?include_archived=1`.
     */
    public function update(UpdateCardRequest $request, int $project, KanbanBoard $board, KanbanColumn $column, KanbanCard $card): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);
        $this->authorize('update', $card);

        // Body / due_date can be cleared via explicit `null` or `""`.
        // Use `request->input()` (preserves falsy values) instead of
        // `validated()` (which strips them via `sometimes` rule).
        $updates = [];
        if ($request->has('title')) {
            $updates['title'] = $request->input('title');
        }
        if ($request->has('body')) {
            $updates['body'] = $request->input('body');
        }
        if ($request->has('due_date')) {
            $updates['due_date'] = $request->input('due_date');
        }
        if ($updates !== []) {
            $card->fill($updates)->save();
        }

        return (new CardResource($card->fresh()))->response();
    }

    /**
     * Hard-delete a card (no soft delete). Cascade:
     *   - `kanban_comments` rows (FK CASCADE on `card_id`)
     *   - `kanban_attachments` rows (FK CASCADE on `card_id`)
     *   - Attachment FILES on `local` disk (via the `CascadesKanbanCardFiles` trait —
     *     controller-led cascade — see the trait docblock for the rationale)
     * R1: archived project → 404 unless `?include_archived=1`.
     */
    public function destroy(Request $request, int $project, KanbanBoard $board, KanbanColumn $column, KanbanCard $card): Response
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);

        $this->deleteCardWithFileCascade($card);

        return response()->noContent();
    }

    /**
     * Reorder cards within a column. Position strings are picked via a stable
     * indexed sequence (matches Kanban\ColumnController::reorder's strategy) so the
     * second fetch yields identical order — O(1) per write, no Position::between
     * (and thus no precision-exhaustion risk on bulk reorder).
     * R1: archived project → 404 unless `?include_archived=1`.
     */
    public function reorder(ReorderCardsRequest $request, int $project, KanbanBoard $board, KanbanColumn $column): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);

        $orderedIds = $request->orderedIds();

        DB::transaction(function () use ($orderedIds, $column): void {
            foreach ($orderedIds as $index => $cardId) {
                KanbanCard::query()
                    ->whereKey($cardId)
                    ->where('column_id', $column->id)
                    ->update(['position' => $this->indexedPosition($index)]);
            }
        });

        return response()->json(['data' => ['reordered' => count($orderedIds)]]);
    }
}
