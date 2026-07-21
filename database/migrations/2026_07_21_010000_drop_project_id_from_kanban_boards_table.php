<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Final migration of the kanban-per-task refactor (commit 8):
 *   1. Enforce NOT NULL on `kanban_boards.task_id`.
 *   2. Drop the `project_id` column from `kanban_boards`.
 *
 * Pre-flight invariant (already satisfied by commit 5 + commit 7):
 *   - Every non-deleted board has `task_id` set (commit 5's reparent
 *     migration backfilled the column).
 *   - The active-name uniqueness index lives on `(task_id, LOWER(name))`
 *     (commit 7's swap migration).
 *
 * Rollback (`down()`) re-adds `project_id` as nullable without a FK,
 * which lets a future rollback populate it from `tasks.project_id` via
 * an external SQL script. The data-level reversal is NOT automatic —
 * commit 8's pre-deploy snapshot is the recovery path for production.
 *
 * Why NOT NULL enforcement is safe today:
 *   - The TaskFactory + KanbanBoardFactory both stamp `task_id` on
 *     every board they create, including the legacy `forProject()`
 *     helper via the factory's `afterMaking` hook.
 *   - The DemoProjectSeeder (commit 6 fix) creates a default task
 *     before creating the seed board, so the demo chain is intact.
 *   - All kanban controllers consume the `{task}` URL segment via
 *     `Route::bind('task', …)` and stamp `task_id` on create. There is
 *     no longer a code path that creates a board without a task.
 *
 * Snapshot contract (per commit 8 plan):
 *   `PGPASSWORD=… pg_dump --format=custom --no-owner --no-privileges …
 *    --file=storage/app/private/snapshots/kanban-per-task-before-commit-8.dump`
 *   is taken before this migration runs.
 */
return new class extends Migration
{
    /**
     * Legacy indexes on `kanban_boards` that still reference the soon-to-be
     * dropped `project_id` column. SQLite refuses to drop a column that
     * an index depends on, so we drop these by name first.
     *
     *   - `boards_project_id_position_index` — created by the original
     *     boards migration (Batch 1) as `(project_id, position)`.
     *   - `kanban_boards_trash_index` — created by the soft-delete
     *     migration (Batch 1.4) as `(deleted_at, project_id, position)`.
     *
     * After commit 8 the trash lookup is task-scoped (queries go through
     * `where('task_id', …)`); the dedicated composite index is no longer
     * needed. `down()` re-adds it as `(deleted_at, task_id, position)`
     * — a safe fallback shape.
     */
    private const LEGACY_INDEXES = [
        'boards_project_id_position_index',
        'kanban_boards_trash_index',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('kanban_boards')) {
            return;
        }

        DB::transaction(function (): void {
            // Belt-and-braces assertion: every non-deleted board must
            // already have a task_id. The reparent migration in commit
            // 5 set this, but we double-check before tightening the
            // constraint so the migration fails loudly instead of
            // silently corrupting data.
            $nullActiveBoards = (int) DB::table('kanban_boards')
                ->whereNull('task_id')
                ->whereNull('deleted_at')
                ->count();

            if ($nullActiveBoards !== 0) {
                throw new RuntimeException(sprintf(
                    'Cannot drop kanban_boards.project_id: %d active board(s) still have NULL task_id.',
                    $nullActiveBoards,
                ));
            }

            // Drop the legacy project_id-keyed indexes BEFORE the column
            // drop. SQLite (and some pgsql setups) refuse to drop a
            // column while an index still references it.
            foreach (self::LEGACY_INDEXES as $indexName) {
                DB::statement('DROP INDEX IF EXISTS '.$indexName);
            }

            // Drop the task_id-keyed UNIQUE before the column change().
            // SQLite rebuilds the table on change() and does NOT preserve
            // expression indexes (`LOWER(name)`); re-adding the index
            // AFTER change() would also rebuild the table again.
            // Dropping now lets the change() run cleanly, and we
            // recreate the index (with the LOWER(name) expression) once
            // the schema is in its final form.
            DB::statement('DROP INDEX IF EXISTS kanban_boards_task_name_active_unique');

            Schema::table('kanban_boards', function (Blueprint $table): void {
                // Tighten the FK to NOT NULL. The constraint was added
                // nullable in commit 5; it is now the canonical parent.
                $table->foreignId('task_id')
                    ->nullable(false)
                    ->change();

                // Drop the FK + column. The schema builder auto-detects
                // the FK by the column name + constrained table; Laravel
                // 13 keeps both FK and index aligned.
                $table->dropConstrainedForeignId('project_id');

                // Recreate the trash lookup on task_id instead of
                // project_id so trashed-board listings under
                // /boards/trashed stay efficient post-drop.
                $table->index(['deleted_at', 'task_id', 'position'], 'kanban_boards_trash_index');
            });

            // Re-create the active-name uniqueness index on task_id +
            // LOWER(name). The expression MUST be in the CREATE statement
            // — a plain (task_id, name) index would be case-sensitive
            // and would let "Sprint 1" coexist with "sprint 1", which
            // the rule layer in App\Rules\UniqueActiveBoardName rejects.
            $predicate = in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)
                ? ' WHERE deleted_at IS NULL'
                : '';

            DB::statement(sprintf(
                'CREATE UNIQUE INDEX kanban_boards_task_name_active_unique ON kanban_boards (task_id, LOWER(name))%s',
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
            Schema::table('kanban_boards', function (Blueprint $table): void {
                // Drop the post-commit-8 trash index so the original
                // `(deleted_at, project_id, position)` index can be
                // recreated cleanly below.
                $table->dropIndex('kanban_boards_trash_index');
            });

            Schema::table('kanban_boards', function (Blueprint $table): void {
                $table->foreignId('project_id')
                    ->nullable()
                    ->constrained('projects')
                    ->cascadeOnDelete();

                // The reverse of the FK tighten above. Note this drops
                // the cascade as well, so a down() from a NOT-NULL
                // state may fail if any task_id is now NULL — restore
                // from the snapshot in that case.
                $table->foreignId('task_id')
                    ->nullable()
                    ->change();

                // Recreate the legacy indexes on project_id.
                $table->index(['project_id', 'position'], 'boards_project_id_position_index');
                $table->index(['deleted_at', 'project_id', 'position'], 'kanban_boards_trash_index');
            });
        });
    }
};
