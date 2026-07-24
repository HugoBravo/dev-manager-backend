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
            'priority',
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

it('fills priority and provides default, high, and low factory states', function (): void {
    $task = (new Task)->fill(['priority' => 'LOW']);

    expect($task->priority)->toBe('LOW')
        ->and(Task::factory()->make()->priority)->toBe('MEDIUM')
        ->and(Task::factory()->high()->make()->priority)->toBe('HIGH')
        ->and(Task::factory()->low()->make()->priority)->toBe('LOW');
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

it('updates task priority from HIGH to LOW', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->high()->create(['project_id' => $project->id]);

    Sanctum::actingAs($owner);

    patchJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", [
        'priority' => 'LOW',
    ])->assertOk()
        ->assertJsonPath('data.priority', 'LOW');

    expect($task->fresh()->priority)->toBe('LOW');
});

it('preserves HIGH priority when omitted on update', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->high()->create(['project_id' => $project->id]);

    Sanctum::actingAs($owner);

    patchJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", [
        'description' => 'Priority must remain unchanged.',
    ])->assertOk()
        ->assertJsonPath('data.priority', 'HIGH');

    expect($task->fresh()->priority)->toBe('HIGH');
});

it('rejects invalid priority on update', function (mixed $priority): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->high()->create(['project_id' => $project->id]);

    Sanctum::actingAs($owner);

    patchJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", [
        'priority' => $priority,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['priority']);

    expect($task->fresh()->priority)->toBe('HIGH');
})->with([
    'unsupported value' => 'URGENT',
    'lowercase value' => 'high',
    'null value' => null,
]);

it('creates and shows a task with HIGH priority', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    Sanctum::actingAs($owner);

    $response = postJson("/api/v1/projects/{$project->id}/tasks", [
        'name' => 'Handle production incident',
        'priority' => 'HIGH',
    ])->assertCreated()
        ->assertJsonPath('data.priority', 'HIGH');

    $taskId = $response->json('data.id');

    getJson("/api/v1/projects/{$project->id}/tasks/{$taskId}")
        ->assertOk()
        ->assertJsonPath('data.priority', 'HIGH');

    expect(Task::query()->findOrFail($taskId)->priority)->toBe('HIGH');
});

it('defaults task priority to MEDIUM when omitted on store', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    Sanctum::actingAs($owner);

    $response = postJson("/api/v1/projects/{$project->id}/tasks", [
        'name' => 'Plan routine maintenance',
    ])->assertCreated()
        ->assertJsonPath('data.priority', 'MEDIUM');

    expect(Task::query()->findOrFail($response->json('data.id'))->priority)->toBe('MEDIUM');
});

it('rejects invalid priority on store', function (mixed $priority): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    Sanctum::actingAs($owner);

    postJson("/api/v1/projects/{$project->id}/tasks", [
        'name' => 'Invalid priority task',
        'priority' => $priority,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['priority']);
})->with([
    'unsupported value' => 'URGENT',
    'lowercase value' => 'high',
    'null value' => null,
]);

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

/*
|--------------------------------------------------------------------------
| REQ-TESTS-1 scenario matrix — verify CRITICAL #4
|--------------------------------------------------------------------------
|
| These three scenarios are mandatory per REQ-TESTS-1; the verify report
| observed they were missing from TaskTest. Pre-fix behavior already
| enforces each contract correctly (Slug unique rule in StoreTaskRequest,
| Route::bind('task' | 'project') ownership scoping in AppServiceProvider),
| so the tests assert the documented contract end-to-end at the HTTP
| boundary — they are GREEN from day one and exist to lock the matrix.
|
*/

it('returns 422 on a slug collision within the same project (REQ-TESTS-1)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    Task::factory()->create([
        'project_id' => $project->id,
        'name' => 'Existing Task',
        'slug' => 'duplicate-slug',
    ]);

    Sanctum::actingAs($owner);

    $response = postJson("/api/v1/projects/{$project->id}/tasks", [
        'name' => 'Brand-new Task',
        'slug' => 'duplicate-slug',
    ])->assertUnprocessable();

    $response->assertJsonValidationErrors(['slug']);
});

it('returns 404 when user B reads user A task under user A project (REQ-TESTS-1 / no existence leak)', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->create(['project_id' => $project->id]);

    Sanctum::actingAs($stranger);

    getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}")
        ->assertNotFound();
});

it('returns 404 when the parent project in the URL does not exist (REQ-TESTS-1)', function (): void {
    $owner = User::factory()->create();
    Sanctum::actingAs($owner);

    // 99999 is unbound on a fresh DB — the {project} Route::bind
    // closure returns null and the controller renders 404.
    getJson('/api/v1/projects/99999/tasks/1')
        ->assertNotFound();
});
