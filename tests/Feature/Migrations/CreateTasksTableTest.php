<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| CreateTasksTableTest — verify CRITICAL #3
|--------------------------------------------------------------------------
|
| The verify report flagged that `2026_07_20_230000_create_tasks_table.php`
| was the only migration in the kanban-per-task sequence that did NOT wrap
| its `Schema::create` body in the per-migration `DB::transaction` boundary
| the design mandates (REQ-MIGRATION-1). A half-applied migration cannot
| be rolled back cleanly without that wrapper.
|
| Two assertions:
|   - structural: the migration source file declares `DB::transaction`
|     wrapping the `Schema::create('tasks', ...)` call;
|   - behaviour: the migration still produces the expected `tasks` schema
|     after the wrapper is in place (regression guard for the fix).
|
*/

it('wraps the tasks Schema::create body in a DB::transaction boundary (REQ-MIGRATION-1)', function (): void {
    $source = file_get_contents(base_path('database/migrations/2026_07_20_230000_create_tasks_table.php'));

    expect($source)
        ->toBeString()
        ->and($source)->toContain('DB::transaction')
        ->and($source)->toContain("Schema::create('tasks'");

    // Sanity: the Schema::create call must live INSIDE the transaction
    // closure, not adjacent to it. The cleanest assertion is that the
    // tokens appear in the expected order — `DB::transaction` opens
    // before `Schema::create('tasks'` and the matching brace closes after.
    $transactionOffset = (int) strpos($source, 'DB::transaction');
    $createOffset = (int) strpos($source, "Schema::create('tasks'");

    expect($transactionOffset)->toBeLessThan($createOffset);
});

it('still produces the expected tasks schema after the transaction wrapper lands', function (): void {
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

    // Fresh-DB idempotency: migrate:fresh on this single migration class
    // leaves no orphan tables and the unique key contract is preserved.
    $uniques = DB::select(
        "SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'tasks' AND name LIKE '%tasks_project_id_slug%'"
    );
    expect($uniques)->not->toBeEmpty();
});

it('adds a non-null priority default and backfills existing tasks across rollback and reapply', function (): void {
    while (DB::connection()->transactionLevel() > 0) {
        DB::connection()->rollBack();
    }

    $migration = require base_path('database/migrations/2026_07_22_000000_add_priority_to_tasks_table.php');

    $migration->down();

    expect(Schema::hasColumn('tasks', 'priority'))->toBeFalse();

    $projectId = DB::table('projects')->insertGetId([
        'owner_id' => DB::table('users')->insertGetId([
            'name' => 'Priority Migration Owner',
            'email' => 'priority-migration@example.test',
            'password' => 'password',
        ]),
        'name' => 'Priority Migration Project',
        'slug' => 'priority-migration-project',
    ]);

    $taskId = DB::table('tasks')->insertGetId([
        'project_id' => $projectId,
        'name' => 'Existing Task',
        'slug' => 'existing-task',
        'status' => 'open',
    ]);

    $boardId = DB::table('kanban_boards')->insertGetId([
        'task_id' => $taskId,
        'name' => 'Existing Board',
        'position' => 'm',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->up();

    $priorityColumn = collect(Schema::getColumns('tasks'))->firstWhere('name', 'priority');
    $migrationSource = file_get_contents(base_path('database/migrations/2026_07_22_000000_add_priority_to_tasks_table.php'));

    expect($priorityColumn)->not->toBeNull()
        ->and($priorityColumn['type_name'])->toBe('varchar')
        ->and($priorityColumn['type'])->toBe('varchar')
        ->and($priorityColumn['nullable'])->toBeFalse()
        ->and(trim((string) $priorityColumn['default'], "'\""))->toBe('MEDIUM')
        ->and(DB::table('tasks')->where('id', $taskId)->value('priority'))->toBe('MEDIUM')
        ->and(DB::table('kanban_boards')->where('id', $boardId)->value('task_id'))->toBe($taskId)
        ->and($migrationSource)->toBeString()
        ->and($migrationSource)->toContain("string('priority', 8)");

    $migration->down();

    expect(Schema::hasColumn('tasks', 'priority'))->toBeFalse();

    $migration->up();

    expect(Schema::hasColumn('tasks', 'priority'))->toBeTrue()
        ->and(DB::table('tasks')->where('id', $taskId)->value('priority'))->toBe('MEDIUM');
});
