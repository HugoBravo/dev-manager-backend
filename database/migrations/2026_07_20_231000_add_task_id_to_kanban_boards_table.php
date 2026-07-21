<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACTIVE_BOARD_NAME_INDEX = 'kanban_boards_project_name_active_unique';

    public function up(): void
    {
        DB::transaction(function (): void {
            if (Schema::hasColumn('kanban_boards', 'task_id')) {
                return;
            }

            DB::statement('DROP INDEX IF EXISTS '.self::ACTIVE_BOARD_NAME_INDEX);

            Schema::table('kanban_boards', function (Blueprint $table): void {
                $table->foreignId('task_id')
                    ->nullable()
                    ->constrained('tasks')
                    ->cascadeOnDelete();
                $table->index('task_id');
            });

            $this->createActiveBoardNameIndex();
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            if (! Schema::hasColumn('kanban_boards', 'task_id')) {
                return;
            }

            DB::statement('DROP INDEX IF EXISTS '.self::ACTIVE_BOARD_NAME_INDEX);

            Schema::table('kanban_boards', function (Blueprint $table): void {
                $table->dropForeign(['task_id']);
                $table->dropIndex(['task_id']);
                $table->dropColumn('task_id');
            });

            $this->createActiveBoardNameIndex();
        });
    }

    private function createActiveBoardNameIndex(): void
    {
        $driver = DB::getDriverName();
        $predicate = in_array($driver, ['pgsql', 'sqlite'], true)
            ? ' WHERE deleted_at IS NULL'
            : '';

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX %s ON kanban_boards (project_id, LOWER(name))%s',
            self::ACTIVE_BOARD_NAME_INDEX,
            $predicate,
        ));
    }
};
