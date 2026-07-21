<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kanban_boards') || ! Schema::hasColumn('kanban_boards', 'task_id')) {
            return;
        }

        DB::transaction(function (): void {
            $projectIds = DB::table('kanban_boards')
                ->whereNotNull('project_id')
                ->whereNull('deleted_at')
                ->distinct()
                ->pluck('project_id');

            foreach ($projectIds as $projectId) {
                $task = DB::table('tasks')
                    ->where('project_id', $projectId)
                    ->where('slug', 'default')
                    ->first();

                $taskId = $task?->id;
                if ($taskId === null) {
                    $taskId = DB::table('tasks')->insertGetId([
                        'project_id' => $projectId,
                        'name' => 'Default',
                        'slug' => 'default',
                        'status' => 'open',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('kanban_boards')
                    ->where('project_id', $projectId)
                    ->whereNull('task_id')
                    ->update(['task_id' => $taskId]);
            }

            $unparented = DB::table('kanban_boards')
                ->whereNull('task_id')
                ->whereNull('deleted_at')
                ->count();

            if ($unparented !== 0) {
                throw new RuntimeException("Kanban board reparenting left {$unparented} active boards without a task.");
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('kanban_boards') || ! Schema::hasColumn('kanban_boards', 'task_id')) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('kanban_boards')->update(['task_id' => null]);
        });
    }
};
