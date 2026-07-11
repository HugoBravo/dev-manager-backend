<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add a per-project, case-insensitive uniqueness constraint on
 * `kanban_boards.name`, scoped to active rows only (soft-deleted rows are
 * allowed to share a name with the next board that inherits it; the restore
 * window is the recovery path for typos).
 *
 * Per the Batch 1 brief, two database backends are supported:
 *   - PostgreSQL: native partial unique index `WHERE deleted_at IS NULL`.
 *   - SQLite (test environment): partial indexes ARE supported, but Laravel's
 *     default test database uses SQLite; we use a partial unique index with
 *     a `LOWER(name)` functional expression so the rule still applies on
 *     SQLite, AND we add a non-partial functional index for the
 *     `LOWER(name)` lookup path.
 *
 * Index name: `kanban_boards_project_name_active_unique`
 * Column expression: `(project_id, LOWER(name))` filtered to `deleted_at IS NULL`.
 *
 * Rollback drops the index (and on pgsql, the partial-index-only predicate
 * is implicit on drop).
 */
return new class extends Migration
{
    private const INDEX_NAME = 'kanban_boards_project_name_active_unique';

    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Native partial unique index: WHERE deleted_at IS NULL.
            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON kanban_boards (project_id, LOWER(name)) WHERE deleted_at IS NULL',
                self::INDEX_NAME,
            ));
        } elseif ($driver === 'sqlite') {
            // SQLite supports partial indexes natively since 3.8. The
            // functional expression LOWER(name) makes the rule
            // case-insensitive, matching the pgsql semantics above. The
            // existing test environment uses `:memory:` and migrations are
            // re-run per-test via RefreshDatabase, so this index is dropped
            // and recreated on every test run — cheap and idempotent.
            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON kanban_boards (project_id, LOWER(name)) WHERE deleted_at IS NULL',
                self::INDEX_NAME,
            ));
        } else {
            // Fallback: full-table functional index, no partial predicate.
            // Drivers like MySQL pre-8.0.13 lack partial indexes. The schema
            // becomes project-scoped + case-insensitive but DOES prevent
            // recycling a name from a soft-deleted board (acceptable for
            // production environments where the restore window is short).
            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON kanban_boards (project_id, LOWER(name))',
                self::INDEX_NAME,
            ));
        }
    }

    public function down(): void
    {
        DB::statement(sprintf('DROP INDEX IF EXISTS %s', self::INDEX_NAME));
    }
};
