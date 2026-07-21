<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kanban;

use App\Http\Controllers\Api\V1\Kanban\Concerns\ResolvesKanbanChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kanban\StoreCommentRequest;
use App\Http\Requests\Kanban\UpdateCommentRequest;
use App\Http\Resources\Kanban\CommentResource;
use App\Models\KanbanBoard;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Models\KanbanComment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CommentController — card comments under
 * /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards/{card}/comments
 *
 * Authorization:
 *  - view (`index`, `show`): ownership via `Route::bind('comment', ...)` and
 *    `Route::bind('card', ...)` -> 404 cross-owner
 *  - create / update / destroy: `authorize(...)` on the policy raises 403 for
 *    cross-author actions INSIDE an owned project (the documented 403 exception)
 *  - edit window: 422 validation error via `Kanban\UpdateCommentRequest::withValidator`
 *
 * Thread semantics: thread-per-author. `parent_id` is enforced at the validation
 * layer (same-card + same-author). Cross-author replies create a NEW top-level
 * root (`parent_id` null). The front-end groups siblings into thread views.
 *
 * R1 (Batch 7): every action respects the project-level `archived_at`
 * via the `KanbanRequestScope` helper exposed by `ResolvesKanbanChain`.
 */
final class CommentController extends Controller
{
    use ResolvesKanbanChain;

    /**
     * List comments. Pagination page[size]=25 per the spec. Optional
     * `?parent_id=` filter (returns the children of a specific parent root).
     * R1: archived project → empty list unless `?include_archived=1`.
     */
    public function index(Request $request, Project $project, Task $task, KanbanBoard $board, KanbanColumn $column, KanbanCard $card): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);

        if (! $this->includeArchived($request) && $projectModel->archived_at !== null) {
            return CommentResource::collection(
                KanbanComment::query()->whereRaw('1 = 0')->paginate(25)
            )->response();
        }

        $query = KanbanComment::query()->where('card_id', $card->id);

        if ($request->filled('parent_id')) {
            $query->where('parent_id', (int) $request->input('parent_id'));
        }

        $comments = $query
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(25);

        return CommentResource::collection($comments)->response();
    }

    /**
     * Show a single comment. Cross-owner -> 404.
     * R1: archived project → 404 unless `?include_archived=1`.
     */
    public function show(Request $request, Project $project, Task $task, KanbanBoard $board, KanbanColumn $column, KanbanCard $card, KanbanComment $comment): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project->getKey());

        // The Route::bind('comment') closure already verified ownership through
        // the card chain. We additionally ensure the comment belongs to the
        // URL card so a cross-card request still 404s.
        if ($comment->card_id !== $card->id) {
            abort(404);
        }

        return (new CommentResource($comment))->response();
    }

    /**
     * Create a top-level OR thread-reply comment. 422 on cross-card /
     * cross-author parent_id. The author is the authenticated user.
     * R1: archived project → 404 unless `?include_archived=1`.
     */
    public function store(StoreCommentRequest $request, Project $project, Task $task, KanbanBoard $board, KanbanColumn $column, KanbanCard $card): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project->getKey());
        $this->authorize('create', KanbanComment::class);

        $payload = [
            'card_id' => $card->id,
            'author_id' => $request->user()->id,
            'parent_id' => $request->validated('parent_id'),
            'body' => $request->validated('body'),
        ];

        $comment = KanbanComment::query()->create($payload);

        return (new CommentResource($comment))->response()->setStatusCode(201);
    }

    /**
     * Edit a comment. The time window + author-vs-author checks happen in
     * Kanban\UpdateCommentRequest::withValidator() and the policy respectively.
     * R1: archived project → 404 unless `?include_archived=1`.
     */
    public function update(UpdateCommentRequest $request, Project $project, Task $task, KanbanBoard $board, KanbanColumn $column, KanbanCard $card, KanbanComment $comment): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project->getKey());

        if ($comment->card_id !== $card->id) {
            abort(404);
        }

        $this->authorize('update', $comment);

        // `request->input()` preserves the falsy-vs-empty semantics the
        // body rule already constrained.
        $comment->body = $request->input('body');
        $comment->save();

        return (new CommentResource($comment->fresh()))->response();
    }

    /**
     * Delete a comment by its author. The window check inside
     * `Kanban\UpdateCommentRequest` does NOT apply on `destroy` — by design we
     * inline the same window check here so a destroy outside the window
     * returns 422 instead of silently succeeding.
     * R1: archived project → 404 unless `?include_archived=1`.
     */
    public function destroy(Request $request, Project $project, Task $task, KanbanBoard $board, KanbanColumn $column, KanbanCard $card, KanbanComment $comment): Response
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project->getKey());

        if ($comment->card_id !== $card->id) {
            abort(404);
        }

        $this->authorize('delete', $comment);

        $windowMinutes = (int) config('kanban.comment_edit_window_minutes');
        $minutesSinceCreation = $comment->created_at?->diffInMinutes(now()) ?? 0;

        if ($minutesSinceCreation > $windowMinutes) {
            return response()->json([
                'message' => "Comment delete window of {$windowMinutes} minute(s) has expired.",
                'errors' => ['body' => ["Comment delete window of {$windowMinutes} minute(s) has expired."]],
            ], 422);
        }

        $comment->delete();

        return response()->noContent();
    }
}
