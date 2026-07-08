<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kanban;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kanban\StoreKanbanLabelRequest;
use App\Http\Requests\Kanban\UpdateKanbanLabelRequest;
use App\Http\Resources\Kanban\KanbanLabelResource;
use App\Models\KanbanLabel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * KanbanLabelController — global CRUD for the authenticated user's labels.
 *
 * Labels are NOT scoped to a project. A user has one set of labels and
 * can apply them to any card in any of their projects. The route prefix
 * is therefore `/api/v1/kanban-labels` (not nested under
 * `/projects/{project}/kanban/...`).
 *
 * Authorization pattern: every action scopes by `user_id = auth()->id()`
 * to enforce visibility. There is no `Route::bind('label', ...)` closure
 * — the `{label}` parameter is resolved through Laravel's default implicit
 * binding AND a `whereKey` filter inside the controller (defence in depth).
 */
final class KanbanLabelController extends Controller
{
    /**
     * List the authenticated user's labels. Paginated, page size 25
     * (Laravel default).
     */
    public function index(Request $request): JsonResponse
    {
        $labels = KanbanLabel::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->paginate(25);

        return KanbanLabelResource::collection($labels)->response();
    }

    /**
     * Create a new label for the authenticated user.
     */
    public function store(StoreKanbanLabelRequest $request): JsonResponse
    {
        $label = KanbanLabel::query()->create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'color' => $request->validated('color'),
        ]);

        return (new KanbanLabelResource($label))->response()->setStatusCode(201);
    }

    /**
     * Show a single label owned by the authenticated user.
     * Cross-user fetch returns 404 (default Laravel not-found body).
     */
    public function show(Request $request, KanbanLabel $label): JsonResponse
    {
        $this->ensureOwned($request, $label);

        return (new KanbanLabelResource($label))->response();
    }

    /**
     * Update an existing label. Both `name` and `color` are optional.
     */
    public function update(UpdateKanbanLabelRequest $request, KanbanLabel $label): JsonResponse
    {
        $this->ensureOwned($request, $label);

        $label->fill($request->validated())->save();

        return (new KanbanLabelResource($label->fresh()))->response();
    }

    /**
     * Hard-delete a label. The pivot rows in `kanban_card_label` are
     * removed automatically by the FK CASCADE on `label_id`. Cards are
     * NOT deleted — the label simply disappears from them.
     */
    public function destroy(Request $request, KanbanLabel $label): Response
    {
        $this->ensureOwned($request, $label);

        $label->delete();

        return response()->noContent();
    }

    /**
     * 404 (ModelNotFoundException) if the label does not belong to the
     * authenticated user. Defence in depth: even though the
     * `Rule::exists(...)->where('user_id', ...)` rule in
     * UpdateKanbanLabelRequest blocks cross-user writes, the read
     * endpoints (`show`, `update`, `destroy`) also need this guard.
     */
    private function ensureOwned(Request $request, KanbanLabel $label): void
    {
        if ($label->user_id !== $request->user()->id) {
            throw (new ModelNotFoundException)
                ->setModel(KanbanLabel::class, [$label->getRouteKey()]);
        }
    }
}
