<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Swap the active-name uniqueness index on `kanban_boards` from
 * `(project_id, LOWER(name))` to `(task_id, LOWER(name))`.
 *
 * Why now (kanban-per-task, commit 7):
 *   - The `UniqueActiveBoardName` rule and the `BoardResource` resource
 *     both key off `task_id` after the kanban-per-task refactor.
 *   - The legacy `kanban_boards_project_name_active_unique` index still
 *     keyed off `project_id` (it survived the bridge migration in commit
 *     5). It enforced the old project-scoped name uniqueness.
 *   - With the rule + resource scoped to task, keeping the DB index on
 *     project_id would cause a mismatch: the rule would let two boards
 *     with the same name under different tasks of the same project
 *     through validation, but the index would then raise a 500 on the
 *     insert.
 *   - The `kanban-per-task` design locks Q3 = many-boards-per-task
 *     allowed, so the project-scoping is no longer the right semantics.
 *
 * Partial-index predicate (`WHERE deleted_at IS NULL`) matches the
 * pre-existing partial unique index — soft-deleted boards keep their
 * names available for restore / recycle.
 *
 * The `task_id` column is still nullable at this point in the kanban
 * refactor; the index tolerates NULLs on its first key (NULL != NULL
 * in SQL unique semantics), so any board still missing a `task_id` is
 * allowed. Commit 8 enforces NOT NULL + drops `project_id` and rewrites
 * the index a final time so the DB catches up with the rule.
 */
return new class extends Migration
{
    private const OLD_INDEX = 'kanban_boards_project_name_active_unique';

    private const NEW_INDEX = 'kanban_boards_task_name_active_unique';

    public function up(): void
    {
        if (! Schema::hasTable('kanban_boards')) {
            return;
        }

        DB::transaction(function (): void {
            DB::statement('DROP INDEX IF EXISTS '.self::OLD_INDEX);

            $driver = DB::getDriverName();
            $predicate = in_array($driver, ['pgsql', 'sqlite'], true)
                ? ' WHERE deleted_at IS NULL'
                : '';

            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON kanban_boards (task_id, LOWER(name))%s',
                self::NEW_INDEX,
                $predicate,
            ));
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('kanban_boards')) {
            return;
        }

        DB::transaction(function (): void {
            DB::statement('DROP INDEX IF EXISTS '.self::NEW_INDEX);

            $driver = DB::getDriverName();
            $predicate = in_array($driver, ['pgsql', 'sqlite'], true)
                ? ' WHERE deleted_at IS NULL'
                : '';

            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON kanban_boards (project_id, LOWER(name))%s',
                self::OLD_INDEX,
                $predicate,
            ));
        });
    }
};
