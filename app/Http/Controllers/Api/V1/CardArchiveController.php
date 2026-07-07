<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesKanbanChain;
use App\Http\Controllers\Controller;
use App\Http\Resources\CardResource;
use App\Models\Board;
use App\Models\Card;
use App\Models\KanbanColumn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CardArchiveController — archive / restore verbs.
 *
 * Split from CardController so the CRUDRoot remains focused; archive is a
 * UI-level flag (`archived_at` is the column the brief explicitly mentions).
 * Both verbs are idempotent — calling archive on an already-archived card
 * returns the unchanged resource; restore on a non-archived card is a no-op.
 */
final class CardArchiveController extends Controller
{
    use ResolvesKanbanChain;

    /**
     * Archive a card (sets `archived_at` to now).
     */
    public function archive(Request $request, int $project, Board $board, KanbanColumn $column, Card $card): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);
        $this->authorize('archive', $card);

        if ($card->archived_at === null) {
            $card->archived_at = now();
            $card->save();
        }

        return (new CardResource($card->fresh()))->response();
    }

    /**
     * Restore an archived card (clears `archived_at`).
     */
    public function restore(Request $request, int $project, Board $board, KanbanColumn $column, Card $card): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);
        $this->authorize('restore', $card);

        if ($card->archived_at !== null) {
            $card->archived_at = null;
            $card->save();
        }

        return (new CardResource($card->fresh()))->response();
    }
}
