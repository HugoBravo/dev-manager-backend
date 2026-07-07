<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CascadesCardFiles;
use App\Http\Controllers\Api\V1\Concerns\ComputesKanbanPositions;
use App\Http\Controllers\Api\V1\Concerns\ResolvesKanbanChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderCardsRequest;
use App\Http\Requests\StoreCardRequest;
use App\Http\Requests\UpdateCardRequest;
use App\Http\Resources\CardResource;
use App\Models\Board;
use App\Models\Card;
use App\Models\KanbanColumn;
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
 */
final class CardController extends Controller
{
    use CascadesCardFiles;
    use ComputesKanbanPositions;
    use ResolvesKanbanChain;

    /**
     * List cards of a column. `?archived=1` includes archived cards (default
     * hides them). Cross-owner → 404 via Route::bind.
     */
    public function index(Request $request, int $project, Board $board, KanbanColumn $column): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);

        $includeArchived = $request->boolean('archived');

        $query = Card::query()->where('column_id', $column->id);

        if (! $includeArchived) {
            $query->whereNull('archived_at');
        }

        $cards = $query->orderBy('position')->paginate(25);

        return CardResource::collection($cards)->response();
    }

    /**
     * Create a card. Position assigned via `Position::after(rightmost)` —
     * appending to the bottom of the column.
     */
    public function store(StoreCardRequest $request, int $project, Board $board, KanbanColumn $column): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);

        $card = Card::query()->create([
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
     */
    public function show(Request $request, int $project, Board $board, KanbanColumn $column, Card $card): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);

        return (new CardResource($card))->response();
    }

    /**
     * Update title / body / due_date on a card. Cross-owner → 404.
     */
    public function update(UpdateCardRequest $request, int $project, Board $board, KanbanColumn $column, Card $card): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);
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
     *   - `card_comments` rows (FK CASCADE on `card_id`)
     *   - `card_attachments` rows (FK CASCADE on `card_id`)
     *   - Attachment FILES on `local` disk (via the `CascadesCardFiles` trait —
     *     controller-led cascade — see the trait docblock for the rationale)
     */
    public function destroy(Request $request, int $project, Board $board, KanbanColumn $column, Card $card): Response
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);

        $this->deleteCardWithFileCascade($card);

        return response()->noContent();
    }

    /**
     * Reorder cards within a column. Position strings are picked via a stable
     * indexed sequence (matches ColumnController::reorder's strategy) so the
     * second fetch yields identical order — O(1) per write, no Position::between
     * (and thus no precision-exhaustion risk on bulk reorder).
     */
    public function reorder(ReorderCardsRequest $request, int $project, Board $board, KanbanColumn $column): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);

        $orderedIds = $request->orderedIds();

        DB::transaction(function () use ($orderedIds, $column): void {
            foreach ($orderedIds as $index => $cardId) {
                Card::query()
                    ->whereKey($cardId)
                    ->where('column_id', $column->id)
                    ->update(['position' => $this->indexedPosition($index)]);
            }
        });

        return response()->json(['data' => ['reordered' => count($orderedIds)]]);
    }
}
