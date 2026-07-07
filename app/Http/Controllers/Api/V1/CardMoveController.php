<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ComputesKanbanPositions;
use App\Http\Controllers\Api\V1\Concerns\ResolvesKanbanChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\MoveCardRequest;
use App\Http\Resources\CardResource;
use App\Models\Board;
use App\Models\Card;
use App\Models\KanbanColumn;
use App\Models\Project;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

/**
 * CardMoveController — cross-column move verb.
 *
 * Split from CardController so the move's owner-scoped target lookup is
 * concentrated in one place. Cross-column target lookup mirrors the Batch 3
 * R3 convention: cross-project target columns return 404 (NOT 422) so the
 * existence-leak contract is uniform across move verbs in the API.
 *
 * Move within the same column is a no-op (the controller returns the
 * resource unchanged) — the test suite may exercise a same-column move.
 *
 * R1 (Batch 7): moving a card of an archived project returns 404 unless
 * the caller passes `?include_archived=1`.
 */
final class CardMoveController extends Controller
{
    use ComputesKanbanPositions;
    use ResolvesKanbanChain;

    /**
     * Move a card cross-column. Target is owner-scoped; cross-project or
     * cross-owner target returns 404 (Batch 3 R3 mirror).
     */
    public function move(
        MoveCardRequest $request,
        int $project,
        Board $board,
        KanbanColumn $column,
        Card $card,
    ): JsonResponse {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);
        $this->authorize('move', $card);

        $targetColumnId = $request->targetColumnId();

        if ($targetColumnId === $column->id) {
            return (new CardResource($card->fresh()))->response();
        }

        $targetColumn = KanbanColumn::query()
            ->whereHas('board.project', function ($q) use ($request): void {
                $q->where('owner_id', $request->user()->id);
            })
            ->whereKey($targetColumnId)
            ->first();

        if ($targetColumn === null) {
            throw (new ModelNotFoundException)->setModel(KanbanColumn::class, [$targetColumnId]);
        }

        // Cross-board target column → 404 (mirror Batch 3 R3).
        if ($targetColumn->board_id !== $board->id) {
            throw (new ModelNotFoundException)->setModel(KanbanColumn::class, [$targetColumnId]);
        }

        $card->column_id = $targetColumn->id;
        $card->position = $this->nextPositionForColumn($targetColumn->id);
        $card->save();

        return (new CardResource($card->fresh()))->response();
    }
}
