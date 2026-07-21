<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Routing\Router;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

it('registers a dedicated task route binder', function (): void {
    expect(app(Router::class)->getBindingCallback('task'))
        ->toBeInstanceOf(Closure::class);
});

it('returns 404 when a task is requested through a project owned by another user', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->create(['project_id' => $project->id]);

    Sanctum::actingAs($stranger);

    getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}")
        ->assertNotFound();
});

it('returns 404 when a task is requested through a different project', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $otherProject = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->create(['project_id' => $project->id]);

    Sanctum::actingAs($owner);

    getJson("/api/v1/projects/{$otherProject->id}/tasks/{$task->id}")
        ->assertNotFound();
});
