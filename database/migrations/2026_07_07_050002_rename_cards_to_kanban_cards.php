<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Rename `cards` -> `kanban_cards` (kanban-rename chore).
 *
 * The corresponding model `KanbanCard` already declares `protected $table = 'kanban_cards'`.
 * FK column names (e.g. `column_id`, `card_id`) on related tables are unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('cards', 'kanban_cards');
    }

    public function down(): void
    {
        Schema::rename('kanban_cards', 'cards');
    }
};
