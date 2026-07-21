<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Exceptions\Tasks\TaskHasActiveBoardsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class TaskController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $project = $this->resolveProject($request, $project);

        $query = $project->tasks()->orderBy('id');
        if (! $request->boolean('include_archived')) {
            $query->whereNull('archived_at');
        }

        return TaskResource::collection($query->paginate(25))->response();
    }

    public function store(StoreTaskRequest $request, Project $project): JsonResponse
    {
        $project = $this->resolveProject($request, $project);
        $this->authorize('create', [Task::class, $project]);
        $validated = $request->validated();

        $task = $project->tasks()->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'open',
        ]);

        return (new TaskResource($task))->response()->setStatusCode(201);
    }

    public function show(Request $request, Project $project, Task $task): JsonResponse
    {
        $project = $this->resolveProject($request, $project);
        $this->ensureTaskBelongsToProject($task, $project);
        $this->ensureTaskIsVisible($request, $task);
        $this->authorize('view', $task);

        return (new TaskResource($task))->response();
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task): JsonResponse
    {
        $project = $this->resolveProject($request, $project);
        $this->ensureTaskBelongsToProject($task, $project);
        $this->authorize('update', $task);

        $task->fill($request->validated())->save();

        return (new TaskResource($task->fresh()))->response();
    }

    public function archive(Request $request, Project $project, Task $task): JsonResponse
    {
        $project = $this->resolveProject($request, $project);
        $this->ensureTaskBelongsToProject($task, $project);
        $this->ensureTaskIsVisible($request, $task);

        $decision = Gate::inspect('archive', $task);
        if ($decision->denied()) {
            throw new TaskHasActiveBoardsException($task);
        }

        $task->archive();

        return (new TaskResource($task->fresh()))->response();
    }

    public function restore(Request $request, Project $project, Task $task): JsonResponse
    {
        $project = $this->resolveProject($request, $project);
        $this->ensureTaskBelongsToProject($task, $project);
        $this->authorize('update', $task);

        $task->restore();

        return (new TaskResource($task->fresh()))->response();
    }

    private function resolveProject(Request $request, Project $project): Project
    {
        if ($project->owner_id !== $request->user()->id) {
            throw (new ModelNotFoundException)->setModel(Project::class, [$project->getRouteKey()]);
        }

        $this->authorize('view', $project);

        return $project;
    }

    private function ensureTaskBelongsToProject(Task $task, Project $project): void
    {
        if ($task->project_id !== $project->id) {
            throw (new ModelNotFoundException)->setModel(Task::class, [$task->getRouteKey()]);
        }
    }

    private function ensureTaskIsVisible(Request $request, Task $task): void
    {
        if ($task->archived_at !== null && ! $request->boolean('include_archived')) {
            throw (new ModelNotFoundException)->setModel(Task::class, [$task->getRouteKey()]);
        }
    }
}
