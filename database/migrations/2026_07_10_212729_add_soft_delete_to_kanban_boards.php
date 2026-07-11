<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `deleted_at` to `kanban_boards` so soft-deletion kicks the row out of
 * default listings and `KanbanBoard::query()` while preserving the row for the
 * restore window and audit trail.
 *
 * The composite index `(deleted_at, project_id, position)` is the only path
 * the Kanban restore/trash flows will scan; pre-indexing it here keeps
 * `KanbanBoard::onlyTrashed()->where('project_id', ...)->orderByDesc('deleted_at')`
 * a single index seek in Batch 3 without a follow-up migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kanban_boards', function (Blueprint $table): void {
            $table->softDeletes();

            // Composite index covering the trashed-list hot path. Keeps Batch 3
            // `GET /projects/{id}/boards/trashed` to a single index seek without
            // a follow-up schema change.
            $table->index(['deleted_at', 'project_id', 'position'], 'kanban_boards_trash_index');
        });
    }

    public function down(): void
    {
        Schema::table('kanban_boards', function (Blueprint $table): void {
            $table->dropIndex('kanban_boards_trash_index');
            $table->dropSoftDeletes();
        });
    }
};
