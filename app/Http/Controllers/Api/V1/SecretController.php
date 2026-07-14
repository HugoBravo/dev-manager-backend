<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSecretRequest;
use App\Http\Requests\UpdateSecretRequest;
use App\Http\Resources\SecretResource;
use App\Models\Project;
use App\Models\Secret;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SecretController extends Controller
{
    /**
     * List the secrets of an owned project (paginated, page[size]=25).
     * Cross-owner resolves to 404 via the `Route::bind('project', ...)`
     * closure in AppServiceProvider; the controller re-checks ownership
     * belt-and-braces.
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);

        $secrets = Secret::query()
            ->where('project_id', $projectModel->id)
            ->orderBy('id')
            ->paginate(25);

        return SecretResource::collection($secrets)->response();
    }

    /**
     * Create a secret under the owned project.
     */
    public function store(StoreSecretRequest $request, Project $project): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);

        $secret = Secret::query()->create([
            'project_id' => $projectModel->id,
            'key' => $request->validated('key'),
            'value' => $request->validated('value'),
            'description' => $request->validated('description'),
        ]);

        return (new SecretResource($secret))->response()->setStatusCode(201);
    }

    /**
     * Show a single secret. Cross-owner resolves to 404 (no existence leak).
     */
    public function show(Request $request, Project $project, Secret $secret): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);

        if ($secret->project_id !== $projectModel->id) {
            throw (new ModelNotFoundException)->setModel(Secret::class, [$secret->getRouteKey()]);
        }

        $this->ensureViewable($request, $secret);

        return (new SecretResource($secret))->response();
    }

    /**
     * Update a secret value and/or description. Cross-owner resolves to 404.
     */
    public function update(UpdateSecretRequest $request, Project $project, Secret $secret): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);

        if ($secret->project_id !== $projectModel->id) {
            throw (new ModelNotFoundException)->setModel(Secret::class, [$secret->getRouteKey()]);
        }

        $this->ensureUpdatable($request, $secret);

        $secret->fill($request->validated())->save();

        return (new SecretResource($secret->fresh()))->response();
    }

    /**
     * Delete a secret. Cross-owner resolves to 404.
     */
    public function destroy(Request $request, Project $project, Secret $secret): Response
    {
        $projectModel = $this->resolveOwnedProject($request, $project);

        if ($secret->project_id !== $projectModel->id) {
            throw (new ModelNotFoundException)->setModel(Secret::class, [$secret->getRouteKey()]);
        }

        $this->ensureDeletable($request, $secret);

        $secret->delete();

        return response()->noContent();
    }

    private function resolveOwnedProject(Request $request, Project $project): Project
    {
        if ($project->owner_id !== $request->user()->id) {
            throw (new ModelNotFoundException)->setModel(Project::class, [$project->getRouteKey()]);
        }

        return $project;
    }

    private function ensureViewable(Request $request, Secret $secret): void
    {
        try {
            $this->authorize('view', $secret);
        } catch (AuthorizationException) {
            throw (new ModelNotFoundException)->setModel(Secret::class, [$secret->getRouteKey()]);
        }
    }

    private function ensureUpdatable(Request $request, Secret $secret): void
    {
        try {
            $this->authorize('update', $secret);
        } catch (AuthorizationException) {
            throw (new ModelNotFoundException)->setModel(Secret::class, [$secret->getRouteKey()]);
        }
    }

    private function ensureDeletable(Request $request, Secret $secret): void
    {
        try {
            $this->authorize('delete', $secret);
        } catch (AuthorizationException) {
            throw (new ModelNotFoundException)->setModel(Secret::class, [$secret->getRouteKey()]);
        }
    }
}
