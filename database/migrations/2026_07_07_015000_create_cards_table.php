<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * sdd/kanban/design §2 — `cards` table. Column_id FK cascade so deleting a
     * column (Batch 3) cascades cards; cross-column move re-points column_id
     * but never deletes. Batch 5 will add comments ON DELETE CASCADE; Batch 6
     * will add attachments ON DELETE CASCADE — both wired here in one shot.
     *
     * Markdown body is stored RAW (no server-side sanitization) per spec §6:
     * TEXT column is the canonical cap; FormRequest enforces 65,535 chars at
     * the HTTP boundary. No HTML rendering on the server.
     *
     * `archived_at` is a UI-level filter column (not a soft delete). Hard
     * delete via `card->delete()` is the lifecycle's terminal state — DB-level
     * cascadeOnDelete clears child rows in comments/attachments when those
     * tables ship. No `deleted_at` column in v1 (sdd/kanban/design §9).
     */
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('column_id')
                ->constrained('kanban_columns')
                ->cascadeOnDelete();

            $table->string('title', 255);
            $table->text('body')->nullable();
            // Fractional-indexing position string (base-26 from Position VO,
            // MAX_LENGTH = 1024 but varchar(255) matches the column convention
            // from kanban_columns; cap is enforced at the value-object layer).
            $table->string('position', 255);

            $table->date('due_date')->nullable();

            $table->timestamp('archived_at')->nullable();

            // Composite indexes aligned with the design spec §2 indexes.
            $table->index(['column_id', 'position']);
            $table->index(['column_id', 'archived_at', 'position']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
