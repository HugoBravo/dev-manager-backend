<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Rename `card_comments` -> `kanban_comments` (kanban-rename chore).
 *
 * The corresponding model `KanbanComment` already declares `protected $table = 'kanban_comments'`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('card_comments', 'kanban_comments');
    }

    public function down(): void
    {
        Schema::rename('kanban_comments', 'card_comments');
    }
};
