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
    public function show(Request $request, Project $project): JsonResponse
    {
        $this->resolveOwnedProject($request, $project);

        if ($project->archived_at !== null && ! $request->boolean('include_archived')) {
            throw (new ModelNotFoundException)->setModel(Project::class, [$project->getRouteKey()]);
        }

        return (new ProjectResource($project))->response();
    }

    /**
     * Update a project. Cross-owner resolves to 404.
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->resolveOwnedProject($request, $project);

        $project->fill($request->validated())->save();

        return (new ProjectResource($project->fresh()))->response();
    }

    /**
     * Delete a project. Cross-owner resolves to 404.
     */
    public function destroy(Request $request, Project $project): Response
    {
        $this->resolveOwnedProject($request, $project);

        $project->delete();

        return response()->noContent();
    }

    /**
     * Fetch the project scoped to the authenticated user. Throws
     * ModelNotFoundException (rendered as 404) for both unknown id AND
     * cross-owner — preventing existence leaks.
     */
    private function resolveOwnedProject(Request $request, Project $project): void
    {
        // Belt-and-braces: the `Route::bind('project', ...)` closure in
        // AppServiceProvider already filters by owner_id, so by the time we
        // get here the project is guaranteed to be owned by auth()->user().
        // We re-check owner_id and re-authorize anyway so the 404-not-403
        // contract (design §7) survives even if a future refactor drops the
        // binding closure's ownership scope. Both the owner mismatch and
        // the policy denial render as ModelNotFound (404) so we never leak
        // existence to a stranger who guesses a slug or id.
        if ($project->owner_id !== $request->user()->id) {
            throw (new ModelNotFoundException)->setModel(Project::class, [$project->getRouteKey()]);
        }

        try {
            $this->authorize('view', $project);
        } catch (AuthorizationException) {
            throw (new ModelNotFoundException)->setModel(Project::class, [$project->getRouteKey()]);
        }
    }
}
