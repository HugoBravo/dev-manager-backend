<?php

declare(strict_types=1);

use App\Models\KanbanBoard;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

it('lists boards through the project task chain', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->create(['project_id' => $project->id]);
    KanbanBoard::factory()->forTask($task)->create(['name' => 'Task Board']);

    Sanctum::actingAs($owner);

    getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/kanban/boards")
        ->assertOk()
        ->assertJsonPath('data.0.data.name', 'Task Board');
});

it('returns 404 when a board is requested under another task', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->create(['project_id' => $project->id]);
    $otherTask = Task::factory()->create(['project_id' => $project->id]);
    $board = KanbanBoard::factory()->forTask($task)->create();

    Sanctum::actingAs($owner);

    getJson("/api/v1/projects/{$project->id}/tasks/{$otherTask->id}/kanban/boards/{$board->id}")
        ->assertNotFound();
});
