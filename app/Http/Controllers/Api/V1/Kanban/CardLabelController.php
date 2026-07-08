<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kanban;

use App\Http\Controllers\Api\V1\Kanban\Concerns\ResolvesKanbanChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kanban\SyncCardLabelsRequest;
use App\Http\Resources\Kanban\CardResource;
use App\Models\KanbanBoard;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

/**
 * Card ↔ Label assignment controller.
 *
 * Lives under the same chain as CardController so the existing
 * {project}/{board}/{column}/{card} Route::bind closures enforce
 * 404-not-403 cross-owner access. The sync operation itself is
 * trivial: it replaces the card's `labels()` relation with the
 * provided id set after verifying every id belongs to the
 * authenticated user (the `SyncCardLabelsRequest` does that — but
 * the controller re-asserts ownership as belt-and-braces).
 */
final class CardLabelController extends Controller
{
    use ResolvesKanbanChain;

    /**
     * PUT /projects/{project}/kanban/boards/{board}/columns/{column}/cards/{card}/labels
     *
     * Body: `{ "label_ids": [1, 2, 3] }`. Replaces the current set
     * of labels on the card with the supplied one. Empty array clears.
     * R1: archived project → 404 unless `?include_archived=1`.
     */
    public function sync(
        SyncCardLabelsRequest $request,
        Project $project,
        KanbanBoard $board,
        KanbanColumn $column,
        KanbanCard $card,
    ): JsonResponse {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project->getKey());

        $card->labels()->sync($request->labelIds());

        return (new CardResource($card->fresh('labels')))->response();
    }
}
