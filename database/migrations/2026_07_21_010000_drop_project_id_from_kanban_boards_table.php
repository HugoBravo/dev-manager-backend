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

        $migrate = function (): void {
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

            foreach (self::LEGACY_INDEXES as $indexName) {
                DB::statement('DROP INDEX IF EXISTS '.$indexName);
            }

            DB::statement('DROP INDEX IF EXISTS kanban_boards_task_name_active_unique');

            if (DB::getDriverName() === 'sqlite') {
                $this->rebuildSqliteTable();
            } else {
                Schema::table('kanban_boards', function (Blueprint $table): void {
                    $table->foreignId('task_id')
                        ->nullable(false)
                        ->change();

                    $table->dropForeign('boards_project_id_foreign');
                    $table->dropColumn('project_id');
                    $table->index(['deleted_at', 'task_id', 'position'], 'kanban_boards_trash_index');
                });
            }

            $predicate = in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)
                ? ' WHERE deleted_at IS NULL'
                : '';

            DB::statement(sprintf(
                'CREATE UNIQUE INDEX kanban_boards_task_name_active_unique ON kanban_boards (task_id, LOWER(name))%s',
                $predicate,
            ));
        };

        if (DB::getDriverName() === 'sqlite') {
            Schema::withoutForeignKeyConstraints(function () use ($migrate): void {
                DB::transaction($migrate);
            });

            return;
        }

        DB::transaction($migrate);
    }

    private function rebuildSqliteTable(): void
    {
        Schema::create('kanban_boards_without_project', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')
                ->constrained('tasks')
                ->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('position', 255);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('kanban_boards_without_project')->insertUsing(
            ['id', 'task_id', 'name', 'position', 'archived_at', 'created_at', 'updated_at', 'deleted_at'],
            DB::table('kanban_boards')->select([
                'id',
                'task_id',
                'name',
                'position',
                'archived_at',
                'created_at',
                'updated_at',
                'deleted_at',
            ]),
        );

        Schema::drop('kanban_boards');
        Schema::rename('kanban_boards_without_project', 'kanban_boards');

        Schema::table('kanban_boards', function (Blueprint $table): void {
            $table->index('task_id', 'kanban_boards_task_id_index');
            $table->index(['deleted_at', 'task_id', 'position'], 'kanban_boards_trash_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('kanban_boards')) {
            return;
        }

        // Drop the post-commit-8 trash index first so the legacy
        // `(deleted_at, project_id, position)` index can be recreated
        // cleanly with the same name below.
        DB::statement('DROP INDEX IF EXISTS kanban_boards_trash_index');

        $migrate = function (): void {
            if (DB::getDriverName() === 'sqlite') {
                $this->rebuildSqliteTableWithProjectId();
            } else {
                Schema::table('kanban_boards', function (Blueprint $table): void {
                    // Re-add `project_id` as nullable, WITHOUT a FK. The
                    // FK is intentionally NOT restored because data may
                    // not be backfilled; an external SQL script must
                    // populate `project_id` from `tasks.project_id` before
                    // re-adding the FK. This matches the snapshot/recovery
                    // contract in the class docblock.
                    $table->foreignId('project_id')->nullable();

                    // Reverse the up() tightening of `task_id` → NOT NULL.
                    $table->foreignId('task_id')->nullable()->change();

                    // Recreate the legacy indexes on `project_id` that
                    // up() dropped.
                    $table->index(['project_id', 'position'], 'boards_project_id_position_index');
                    $table->index(['deleted_at', 'project_id', 'position'], 'kanban_boards_trash_index');
                });

                DB::statement('DROP INDEX IF EXISTS kanban_boards_task_name_active_unique');

                $predicate = in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)
                    ? ' WHERE deleted_at IS NULL'
                    : '';

                DB::statement(sprintf(
                    'CREATE UNIQUE INDEX kanban_boards_task_name_active_unique ON kanban_boards (task_id, LOWER(name))%s',
                    $predicate,
                ));
            }
        };

        if (DB::getDriverName() === 'sqlite') {
            Schema::withoutForeignKeyConstraints(function () use ($migrate): void {
                DB::transaction($migrate);
            });

            return;
        }

        DB::transaction($migrate);
    }

    private function rebuildSqliteTableWithProjectId(): void
    {
        Schema::create('kanban_boards_with_project', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->nullable();
            // `task_id` must be nullable: it was added as nullable by the
            // commit-5 migration and only tightened to NOT NULL by up().
            // down() reverses that tightening by leaving it nullable here.
            $table->foreignId('task_id')
                ->nullable()
                ->constrained('tasks')
                ->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('position', 255);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Insert with an explicit NULL `project_id` because the source
        // table (post up()) has no such column. An external script must
        // backfill `project_id` from `tasks.project_id` before the FK
        // can be re-added.
        DB::table('kanban_boards_with_project')->insertUsing(
            ['id', 'project_id', 'task_id', 'name', 'position', 'archived_at', 'created_at', 'updated_at', 'deleted_at'],
            DB::table('kanban_boards')->selectRaw(
                'id, NULL AS project_id, task_id, name, position, archived_at, created_at, updated_at, deleted_at'
            ),
        );

        Schema::drop('kanban_boards');
        Schema::rename('kanban_boards_with_project', 'kanban_boards');

        Schema::table('kanban_boards', function (Blueprint $table): void {
            $table->index('task_id', 'kanban_boards_task_id_index');
            $table->index(['project_id', 'position'], 'boards_project_id_position_index');
            $table->index(['deleted_at', 'project_id', 'position'], 'kanban_boards_trash_index');
        });

        // Re-create the stable active-name unique index lost when the
        // table was dropped and renamed. The predicate mirrors up().
        DB::statement('DROP INDEX IF EXISTS kanban_boards_task_name_active_unique');

        $predicate = in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)
            ? ' WHERE deleted_at IS NULL'
            : '';

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX kanban_boards_task_name_active_unique ON kanban_boards (task_id, LOWER(name))%s',
            $predicate,
        ));
    }
};
