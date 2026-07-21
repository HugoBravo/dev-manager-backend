<?php

declare(strict_types=1);

use App\Models\KanbanBoard;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

it('creates the task schema with the project-scoped slug contract', function (): void {
    expect(Schema::hasTable('tasks'))->toBeTrue()
        ->and(Schema::hasColumns('tasks', [
            'id',
            'project_id',
            'name',
            'slug',
            'description',
            'status',
            'archived_at',
            'created_at',
            'updated_at',
        ]))->toBeTrue();

    $project = Project::factory()->create();

    Task::factory()->create([
        'project_id' => $project->id,
        'slug' => 'default',
    ]);

    expect(fn (): Task => Task::factory()->create([
        'project_id' => $project->id,
        'slug' => 'default',
    ]))->toThrow(QueryException::class);
});

it('exposes project and board relationships and the required factory states', function (): void {
    $project = Project::factory()->create();
    $task = Task::factory()->default()->create(['project_id' => $project->id]);

    expect($task->project->is($project))->toBeTrue()
        ->and($task->boards())->toBeInstanceOf(HasMany::class)
        ->and(Task::factory()->archived()->make()->archived_at)->not->toBeNull();
});

it('archives and restores a task with no active boards', function (): void {
    $task = Task::factory()->create();

    $task->archive();

    expect($task->fresh()->archived_at)->not->toBeNull()
        ->and(Task::query()->archived()->whereKey($task->id)->exists())->toBeTrue();

    $task->restore();

    expect($task->fresh()->archived_at)->toBeNull();
});

it('lists a project task collection with pagination', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    Task::factory()->count(26)->create(['project_id' => $project->id]);

    Sanctum::actingAs($owner);

    getJson("/api/v1/projects/{$project->id}/tasks")
        ->assertOk()
        ->assertJsonCount(25, 'data')
        ->assertJsonPath('meta.total', 26);
});

it('creates, shows, and updates a task with a server-generated slug', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    Sanctum::actingAs($owner);

    $createResponse = postJson("/api/v1/projects/{$project->id}/tasks", [
        'name' => 'Plan API migration',
        'description' => 'Move the board ownership boundary.',
        'status' => 'in_progress',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Plan API migration')
        ->assertJsonPath('data.slug', 'plan-api-migration')
        ->assertJsonPath('data.status', 'in_progress');

    $taskId = $createResponse->json('data.id');

    getJson("/api/v1/projects/{$project->id}/tasks/{$taskId}")
        ->assertOk()
        ->assertJsonPath('data.id', $taskId);

    patchJson("/api/v1/projects/{$project->id}/tasks/{$taskId}", [
        'name' => 'Plan API rollout',
        'description' => null,
        'status' => 'done',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Plan API rollout')
        ->assertJsonPath('data.slug', 'plan-api-rollout')
        ->assertJsonPath('data.description', null)
        ->assertJsonPath('data.status', 'done');
});

it('rejects invalid task payloads with a validation response', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    Sanctum::actingAs($owner);

    postJson("/api/v1/projects/{$project->id}/tasks", [
        'name' => '',
        'status' => 'blocked',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'status']);
});

it('archives and restores a task through the lifecycle endpoints', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->create(['project_id' => $project->id]);

    Sanctum::actingAs($owner);

    $archiveResponse = postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/archive")
        ->assertOk()
        ->assertJsonStructure(['data' => ['archived_at']]);

    expect($archiveResponse->json('data.archived_at'))->toBeString();

    postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.archived_at', null);
});

it('returns a conflict when archiving a task with an active board', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->create(['project_id' => $project->id]);
    KanbanBoard::factory()->forTask($task)->create();

    Sanctum::actingAs($owner);

    postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/archive")
        ->assertConflict()
        ->assertJsonPath('code', 'task_has_active_boards')
        ->assertJsonPath('task_id', $task->id);
});
