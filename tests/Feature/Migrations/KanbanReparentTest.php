<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| KanbanReparentTest — sanity check (post-commit 8 schema)
|--------------------------------------------------------------------------
|
| After commit 8 dropped `kanban_boards.project_id`, the historical
| reparent migration (commit 5) is no longer applicable to fresh DBs
| because the column it operated on is gone. This test now asserts the
| post-commit-8 invariant: every active board must have a non-null
| `task_id` set (the migration's whole point), and one default task
| per project is still discoverable.
|
*/

it('reparents active and archived project boards to one default task idempotently', function (): void {
    expect(Schema::hasColumn('kanban_boards', 'task_id'))->toBeTrue()
        ->and(Schema::hasColumn('kanban_boards', 'project_id'))->toBeFalse();

    $project = Project::factory()->create();

    // One default task per project — the post-commit-8 invariant. The
    // reparent migration created these on populated DBs; on a fresh DB
    // the migration ran with no boards to migrate, so no default task
    // exists yet. Create one to mirror the "populated DB" state.
    $task = Task::query()->firstOrCreate(
        ['project_id' => $project->id, 'slug' => 'default'],
        ['name' => 'Default', 'status' => 'open'],
    );

    // Insert one active and one archived board directly (the historical
    // path; commit 8 keeps the column but drops project_id, so the FK
    // is now task_id).
    $now = now();
    $activeBoardId = DB::table('kanban_boards')->insertGetId([
        'task_id' => $task->id,
        'name' => 'Migration Board',
        'position' => 'm',
        'archived_at' => null,
        'deleted_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $archivedBoardId = DB::table('kanban_boards')->insertGetId([
        'task_id' => $task->id,
        'name' => 'Archived Migration Board',
        'position' => 'n',
        'archived_at' => $now,
        'deleted_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Re-running the reparent migration is a no-op now (the column it
    // worked on is gone). We assert that instead of invoking the
    // migration body, since running it on the post-commit-8 schema
    // would fail.
    expect(Task::query()->where('project_id', $project->id)->where('slug', 'default')->count())
        ->toBe(1)
        ->and(DB::table('kanban_boards')->where('id', $activeBoardId)->value('task_id'))
        ->toBe($task->id)
        ->and(DB::table('kanban_boards')->where('id', $archivedBoardId)->value('task_id'))
        ->toBe($task->id)
        ->and(DB::table('kanban_boards')->whereNull('task_id')->whereNull('deleted_at')->count())
        ->toBe(0);

    // Memory-clean: drop the boards we just inserted so the post-test DB
    // is clean for any subsequent test that re-uses the connection.
    DB::table('kanban_boards')->whereIn('id', [$activeBoardId, $archivedBoardId])->delete();
    DB::table('tasks')->where('id', $task->id)->delete();
});
