<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `board_audit_logs` — append-only audit trail for board lifecycle events
 * (created / archived / unarchived / reordered / cloned / purged / name_taken /
 * restored). Each row records:
 *   - the board that triggered the event (FK cascade: a force-delete of a
 *     board removes its audit trail; the purge job (Batch 1.9) force-deletes
 *     board rows that have aged out, so the cascade keeps the trail honest);
 *   - the acting user (nullable: cron / purge runs without an actor);
 *   - the canonical `action` string (indexed for fast filtering by event type);
 *   - a `payload` JSON blob carrying event-specific metadata (positions,
 *     source ids, etc.) — shape is documented per call site;
 *   - `created_at` only — audit rows are immutable, no updated_at.
 *
 * Composite index `(board_id, created_at desc)` powers the GET
 * `/boards/{id}/audit` pagination (Batch 2.2) with a single index seek +
 * ORDER BY scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_audit_logs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('board_id')
                ->constrained('kanban_boards')
                ->cascadeOnDelete();

            // Nullable: cron jobs (e.g. PurgeSoftDeletedBoards) record rows
            // with no acting user. The FK is nullOnDelete so deleting a user
            // doesn't destroy their audit history.
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 50)->index();

            $table->json('payload')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Composite for the hot query path:
            //   WHERE board_id = ? ORDER BY created_at DESC
            $table->index(['board_id', 'created_at'], 'board_audit_logs_board_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_audit_logs');
    }
};
