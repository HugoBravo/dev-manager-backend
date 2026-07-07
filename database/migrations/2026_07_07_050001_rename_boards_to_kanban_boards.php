<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Rename `boards` -> `kanban_boards` (kanban-rename chore).
 *
 * The corresponding model `KanbanBoard` already declares `protected $table = 'kanban_boards'`.
 * FK column names on related tables (e.g. `project_id`) are unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('boards', 'kanban_boards');
    }

    public function down(): void
    {
        Schema::rename('kanban_boards', 'boards');
    }
};
