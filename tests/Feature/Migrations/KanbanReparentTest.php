<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('reparents active and archived project boards to one default task idempotently', function (): void {
    expect(Schema::hasColumn('kanban_boards', 'task_id'))->toBeTrue();

    $project = Project::factory()->create();
    $now = now();

    $activeBoardId = DB::table('kanban_boards')->insertGetId([
        'project_id' => $project->id,
        'task_id' => null,
        'name' => 'Migration Board',
        'position' => 'm',
        'archived_at' => null,
        'deleted_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $archivedBoardId = DB::table('kanban_boards')->insertGetId([
        'project_id' => $project->id,
        'task_id' => null,
        'name' => 'Archived Migration Board',
        'position' => 'n',
        'archived_at' => $now,
        'deleted_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $migration = require base_path('database/migrations/2026_07_20_232000_reparent_boards_to_default_task.php');
    $migration->up();
    $migration->up();

    $task = Task::query()
        ->where('project_id', $project->id)
        ->where('slug', 'default')
        ->firstOrFail();

    expect(Task::query()->where('project_id', $project->id)->where('slug', 'default')->count())
        ->toBe(1)
        ->and(DB::table('kanban_boards')->where('id', $activeBoardId)->value('task_id'))
        ->toBe($task->id)
        ->and(DB::table('kanban_boards')->where('id', $archivedBoardId)->value('task_id'))
        ->toBe($task->id)
        ->and(DB::table('kanban_boards')->whereNull('task_id')->whereNull('deleted_at')->count())
        ->toBe(0);
});
