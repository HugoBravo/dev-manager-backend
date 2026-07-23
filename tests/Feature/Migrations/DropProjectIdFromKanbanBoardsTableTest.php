<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

const DROP_PROJECT_ID_MIGRATION = 'database/migrations/2026_07_21_010000_drop_project_id_from_kanban_boards_table.php';

beforeEach(function (): void {
    while ($this->app['db']->connection()->transactionLevel() > 0) {
        $this->app['db']->connection()->rollBack();
    }
});

it('restores the rollback schema and snapshot recovery contract', function (): void {
    expect(Artisan::call('migrate:fresh', ['--force' => true]))->toBe(0)
        ->and(Artisan::call('migrate:rollback', ['--step' => 2, '--force' => true]))->toBe(0);

    $columns = collect(Schema::getColumns('kanban_boards'))->keyBy('name');
    $indexes = collect(Schema::getIndexes('kanban_boards'))->pluck('name');
    $foreignKeys = collect(Schema::getForeignKeys('kanban_boards'))->pluck('name');

    expect($columns->get('project_id')['nullable'])->toBeTrue()
        ->and($columns->get('task_id')['nullable'])->toBeTrue()
        ->and($indexes)->toContain(
            'boards_project_id_position_index',
            'kanban_boards_trash_index',
            'kanban_boards_task_name_active_unique',
            'kanban_boards_task_id_index',
        )
        ->and($foreignKeys)->not->toContain('boards_project_id_foreign');
});

it('reapplies the forward migration without changing the latest schema contract', function (): void {
    expect(Artisan::call('migrate'))->toBe(0);

    $columns = collect(Schema::getColumns('kanban_boards'))->keyBy('name');
    $indexes = collect(Schema::getIndexes('kanban_boards'))->pluck('name');
    $foreignKeys = collect(Schema::getForeignKeys('kanban_boards'))->pluck('name');

    expect($columns)->not->toHaveKey('project_id')
        ->and($columns->get('task_id')['nullable'])->toBeFalse()
        ->and($indexes)->toContain(
            'kanban_boards_task_id_index',
            'kanban_boards_trash_index',
            'kanban_boards_task_name_active_unique',
        )
        ->and($foreignKeys)->not->toContain('boards_project_id_foreign');
});

it('documents snapshot recovery without claiming a factory hook', function (): void {
    $source = file_get_contents(base_path(DROP_PROJECT_ID_MIGRATION));

    expect($source)->toBeString()
        ->and($source)->not->toContain('afterMaking')
        ->and($source)->not->toContain('forProject()')
        ->and($source)->toContain('re-adds `project_id` as nullable without a FK')
        ->and($source)->toContain("commit 8's pre-deploy snapshot is the recovery path for production")
        ->and($source)->toContain('pg_dump --format=custom --no-owner --no-privileges');
});
