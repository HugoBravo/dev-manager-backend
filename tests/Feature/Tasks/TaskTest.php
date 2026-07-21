<?php

declare(strict_types=1);

use App\Exceptions\Tasks\TaskHasActiveBoardsException;
use App\Models\KanbanBoard;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

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

it('rejects archiving a task with an active board', function (): void {
    $project = Project::factory()->create();
    $task = Task::factory()->create(['project_id' => $project->id]);
    KanbanBoard::factory()->forProject($project)->create();

    expect(function () use ($task): void {
        $task->archive();
    })->toThrow(TaskHasActiveBoardsException::class);
});
