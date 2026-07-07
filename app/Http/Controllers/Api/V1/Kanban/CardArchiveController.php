<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kanban;

use App\Http\Controllers\Api\V1\Kanban\Concerns\ResolvesKanbanChain;
use App\Http\Controllers\Controller;
use App\Http\Resources\Kanban\CardResource;
use App\Models\KanbanBoard;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CardArchiveController — archive / restore verbs.
 *
 * Split from CardController so the CRUDRoot remains focused; archive is a
 * UI-level flag (`archived_at` is the column the brief explicitly mentions).
 * Both verbs are idempotent — calling archive on an already-archived card
 * returns the unchanged resource; restore on a non-archived card is a no-op.
 *
 * R1 (Batch 7): archive/restore on cards of an archived project return 404
 * unless the caller passes `?include_archived=1`.
 */
final class CardArchiveController extends Controller
{
    use ResolvesKanbanChain;

    /**
     * Archive a card (sets `archived_at` to now).
     */
    public function archive(Request $request, int $project, KanbanBoard $board, KanbanColumn $column, KanbanCard $card): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);
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
    public function restore(Request $request, int $project, KanbanBoard $board, KanbanColumn $column, KanbanCard $card): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);
        $this->authorize('restore', $card);

        if ($card->archived_at !== null) {
            $card->archived_at = null;
            $card->save();
        }

        return (new CardResource($card->fresh()))->response();
    }
}
