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
        $migrate = function (): void {
            if (! Schema::hasColumn('tasks', 'priority')) {
                Schema::table('tasks', function (Blueprint $table): void {
                    $table->string('priority', 8)->nullable();
                });
            }

            DB::table('tasks')
                ->whereNull('priority')
                ->update(['priority' => 'MEDIUM']);

            Schema::table('tasks', function (Blueprint $table): void {
                $table->string('priority', 8)
                    ->default('MEDIUM')
                    ->nullable(false)
                    ->change();
            });
        };

        if (DB::getDriverName() === 'sqlite') {
            Schema::withoutForeignKeyConstraints(function () use ($migrate): void {
                DB::transaction($migrate);
            });

            return;
        }

        DB::transaction($migrate);
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            if (! Schema::hasColumn('tasks', 'priority')) {
                return;
            }

            Schema::table('tasks', function (Blueprint $table): void {
                $table->dropColumn('priority');
            });
        });
    }
};
