<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secrets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->string('key', 100);
            $table->text('value');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->unique(['project_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secrets');
    }
};
