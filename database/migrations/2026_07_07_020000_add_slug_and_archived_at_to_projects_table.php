<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('slug', 100)->nullable()->unique()->after('name');
            $table->timestamp('archived_at')->nullable()->after('description');

            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['archived_at']);
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'archived_at']);
        });
    }
};
