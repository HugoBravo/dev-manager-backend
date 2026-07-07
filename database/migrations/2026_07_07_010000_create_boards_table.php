<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->string('name', 100);
            // Fractional-indexing lexorank-style position strings; stored as
            // varchar(255) so future renumber has headroom beyond the 1024-byte
            // Position::MAX_LENGTH hard cap (Batch 3 ships the value object).
            $table->string('position', 255);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            // Per-project board listing + stable ordering.
            $table->index(['project_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boards');
    }
};
