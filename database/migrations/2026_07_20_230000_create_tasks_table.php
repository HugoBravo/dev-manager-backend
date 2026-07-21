<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // REQ-MIGRATION-1: each migration in the kanban-per-task sequence
        // wraps its body in a per-migration `DB::transaction` boundary so a
        // partially-applied migration cannot leave the schema in a degraded
        // state — Laravel rolls the whole `up()` back and re-throws on any
        // DDL failure.
        DB::transaction(function (): void {
            Schema::create('tasks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')
                    ->constrained('projects')
                    ->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('slug', 120);
                $table->text('description')->nullable();
                $table->string('status', 20)->default('open');
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->unique(['project_id', 'slug']);
                $table->index(['project_id', 'archived_at']);
            });
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
