<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ProjectController extends Controller
{
    /**
     * List the authenticated user's projects (paginated envelope page[size]=25).
     * Hidden by default: archived (where archived_at IS NOT NULL).
     * Pass `?include_archived=1` to bypass — same convention as Card's
     * `?archived=1` filter from the spec.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Project::query()
            ->where('owner_id', $request->user()->id)
            ->orderBy('id');

        if (! $request->boolean('include_archived')) {
            $query->whereNull('archived_at');
        }

        return ProjectResource::collection($query->paginate(25))->response();
    }

    /**
     * Create a project owned by the authenticated user.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::query()->create([
            'owner_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            // `slug` is optional in the request — the Project model
            // auto-generates a unique slug from `name` when omitted.
            'slug' => $request->validated('slug'),
        ]);

        return (new ProjectResource($project))->response()->setStatusCode(201);
    }

    /**
     * Show one project. Cross-owner resolves to 404 (no existence leak).
     * Archived projects respond 404 by default; pass
     * `?include_archived=1` to view them.
     */
    public function show(Request $request, int $project): JsonResponse
    {
        $model = $this->resolveOwnedProject($request, $project);

        if ($model->archived_at !== null && ! $request->boolean('include_archived')) {
            throw (new ModelNotFoundException)->setModel(Project::class, [$project]);
        }

        return (new ProjectResource($model))->response();
    }

    /**
     * Update a project. Cross-owner resolves to 404.
     */
    public function update(UpdateProjectRequest $request, int $project): JsonResponse
    {
        $model = $this->resolveOwnedProject($request, $project);

        $model->fill($request->validated())->save();

        return (new ProjectResource($model->fresh()))->response();
    }

    /**
     * Delete a project. Cross-owner resolves to 404.
     */
    public function destroy(Request $request, int $project): Response
    {
        $model = $this->resolveOwnedProject($request, $project);

        $model->delete();

        return response()->noContent();
    }

    /**
     * Fetch the project scoped to the authenticated user. Throws
     * ModelNotFoundException (rendered as 404) for both unknown id AND
     * cross-owner — preventing existence leaks.
     */
    private function resolveOwnedProject(Request $request, int $projectId): Project
    {
        $model = Project::query()
            ->where('owner_id', $request->user()->id)
            ->whereKey($projectId)
            ->first();

        if ($model === null) {
            throw (new ModelNotFoundException)->setModel(Project::class, [$projectId]);
        }

        // Belt-and-braces: even if a future change relaxes the WHERE above, the
        // gate still denies. Belt-only would still satisfy the tests, but the
        // 404-not-403 contract (design §7) is enforced here directly.
        try {
            $this->authorize('view', $model);
        } catch (AuthorizationException) {
            throw (new ModelNotFoundException)->setModel(Project::class, [$projectId]);
        }

        return $model;
    }
}
