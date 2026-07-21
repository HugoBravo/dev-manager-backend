<?php

declare(strict_types=1);

use App\Models\KanbanAttachment;
use App\Models\KanbanBoard;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Models\KanbanComment;
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

/*
|--------------------------------------------------------------------------
| Nested index gating — archived task (REQ-MIGRATION-2 / verify CRITICAL #2)
|--------------------------------------------------------------------------
|
| Verify CRITICAL #2: `ensureNotArchivedTask()` was only called for board
| show, leaving board / column / card / comment / attachment *index*
| endpoints exposed when the task is archived. The acceptance scenario
| in REQ-MIGRATION-2 requires 404 on the no-flag path regardless of the
| nested endpoint so the frontend can branch on which layer triggered
| the refresh.
|
*/

it('hides the board index under an archived task (404, no flag)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->default()->create([
        'project_id' => $project->id,
        'archived_at' => now(),
    ]);
    KanbanBoard::factory()->forTask($task)->create();

    getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/kanban/boards", [
        'Authorization' => 'Bearer '.bearerFor($owner),
    ])->assertNotFound();
});

it('shows the board index under an archived task when ?include_archived=1', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->default()->create([
        'project_id' => $project->id,
        'archived_at' => now(),
    ]);
    KanbanBoard::factory()->forTask($task)->create();

    $response = getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/kanban/boards?include_archived=1", [
        'Authorization' => 'Bearer '.bearerFor($owner),
    ])->assertOk();

    expect(collect($response->json('data'))->pluck('data.id')->all())
        ->toHaveCount(1);
});

it('hides the column index under an archived task (404, no flag)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->default()->create([
        'project_id' => $project->id,
        'archived_at' => now(),
    ]);
    $board = KanbanBoard::factory()->forTask($task)->create();
    KanbanColumn::factory()->forBoard($board)->create();

    getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/kanban/boards/{$board->id}/columns", [
        'Authorization' => 'Bearer '.bearerFor($owner),
    ])->assertNotFound();
});

it('shows the column index under an archived task when ?include_archived=1', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->default()->create([
        'project_id' => $project->id,
        'archived_at' => now(),
    ]);
    $board = KanbanBoard::factory()->forTask($task)->create();
    KanbanColumn::factory()->forBoard($board)->create();

    $response = getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/kanban/boards/{$board->id}/columns?include_archived=1", [
        'Authorization' => 'Bearer '.bearerFor($owner),
    ])->assertOk();

    expect(collect($response->json('data'))->pluck('data.id')->all())
        ->toHaveCount(1);
});

it('hides the card index under an archived task (404, no flag)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->default()->create([
        'project_id' => $project->id,
        'archived_at' => now(),
    ]);
    $board = KanbanBoard::factory()->forTask($task)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    KanbanCard::factory()->forColumn($column)->create();

    getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards", [
        'Authorization' => 'Bearer '.bearerFor($owner),
    ])->assertNotFound();
});

it('hides the comment index under an archived task (404, no flag)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->default()->create([
        'project_id' => $project->id,
        'archived_at' => now(),
    ]);
    $board = KanbanBoard::factory()->forTask($task)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();
    KanbanComment::factory()->forCard($card)->create();

    getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments", [
        'Authorization' => 'Bearer '.bearerFor($owner),
    ])->assertNotFound();
});

it('hides the attachment index under an archived task (404, no flag)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->default()->create([
        'project_id' => $project->id,
        'archived_at' => now(),
    ]);
    $board = KanbanBoard::factory()->forTask($task)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();
    KanbanAttachment::factory()->forCard($card)->create();

    getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments", [
        'Authorization' => 'Bearer '.bearerFor($owner),
    ])->assertNotFound();
});
