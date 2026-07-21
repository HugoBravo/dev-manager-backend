<?php

declare(strict_types=1);

use App\Models\KanbanBoard;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

use function Pest\Laravel\getJson;

/*
|--------------------------------------------------------------------------
| RequestScopeTest — kanban-per-task (commit 7)
|--------------------------------------------------------------------------
|
| Asserts the `?include_archived=1` request-scoped convention threads through
| the new task layer introduced by the kanban-per-task refactor. Archived
| tasks with archived boards MUST be reachable only when the flag is set.
|
| The kanban v1 project-level filter is exercised in BoardTest /
| ColumnTest; this file is the task-level counterpart so the convention
| stays enforced end-to-end after the chain reshape.
|
*/

it('hides boards under an archived task when ?include_archived is omitted', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->default()->create([
        'project_id' => $project->id,
        'archived_at' => now(),
    ]);
    $board = KanbanBoard::factory()->forTask($task)->create();

    getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/kanban/boards/{$board->id}", [
        'Authorization' => 'Bearer '.bearerFor($owner),
    ])
        ->assertNotFound();
});

it('shows boards under an archived task when ?include_archived=1', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->default()->create([
        'project_id' => $project->id,
        'archived_at' => now(),
    ]);
    $board = KanbanBoard::factory()->forTask($task)->create();

    getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/kanban/boards/{$board->id}?include_archived=1", [
        'Authorization' => 'Bearer '.bearerFor($owner),
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $board->id);
});

it('hides an archived task from the project task index when ?include_archived is omitted', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    Task::factory()->default()->create(['project_id' => $project->id]); // visible
    $archived = Task::factory()->default()->create([
        'project_id' => $project->id,
        'archived_at' => now(),
        'name' => 'Closed workstream',
        'slug' => 'closed-workstream',
    ]);

    $response = getJson("/api/v1/projects/{$project->id}/tasks", [
        'Authorization' => 'Bearer '.bearerFor($owner),
    ])->assertOk();

    $ids = collect($response->json('data'))->pluck('data.id')->all();
    expect($ids)->not->toContain($archived->id);
});

it('lists archived tasks in the project task index when ?include_archived=1', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    Task::factory()->default()->create(['project_id' => $project->id]); // visible
    $archived = Task::factory()->default()->create([
        'project_id' => $project->id,
        'archived_at' => now(),
        'name' => 'Closed workstream',
        'slug' => 'closed-workstream',
    ]);

    $response = getJson("/api/v1/projects/{$project->id}/tasks?include_archived=1", [
        'Authorization' => 'Bearer '.bearerFor($owner),
    ])->assertOk();

    $ids = collect($response->json('data'))->pluck('data.id')->all();
    expect($ids)->toContain($archived->id);
});

it('still blocks cross-owner access on archived-task URLs (404, not 403)', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->default()->create([
        'project_id' => $project->id,
        'archived_at' => now(),
    ]);
    $board = KanbanBoard::factory()->forTask($task)->create();

    // Stranger requesting the archived-task board even with the opt-in
    // flag — the owner-scope guard fires first and yields 404 (NOT 403).
    getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/kanban/boards/{$board->id}?include_archived=1", [
        'Authorization' => 'Bearer '.bearerFor($stranger),
    ])
        ->assertNotFound();
});
