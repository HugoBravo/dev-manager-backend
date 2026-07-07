<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_columns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('board_id')
                ->constrained('boards')
                ->cascadeOnDelete();
            $table->string('name', 100);
            // Fractional-indexing position strings; varchar(255) leaves headroom
            // beyond the 1024-byte `App\Support\Kanban\Position::MAX_LENGTH`
            // hard cap so future renumber can store wider strings.
            $table->string('position', 255);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            // Composite for `WHERE board_id = ? ORDER BY position` — the
            // single hot query path (column index, column reorder, column
            // move that rewrites positions on the new board).
            $table->index(['board_id', 'position'], 'kanban_columns_board_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_columns');
    }
};
