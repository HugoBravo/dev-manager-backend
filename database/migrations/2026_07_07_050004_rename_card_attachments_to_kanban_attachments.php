<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Rename `card_attachments` -> `kanban_attachments` (kanban-rename chore).
 *
 * The corresponding model `KanbanAttachment` already declares `protected $table = 'kanban_attachments'`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('card_attachments', 'kanban_attachments');
    }

    public function down(): void
    {
        Schema::rename('kanban_attachments', 'card_attachments');
    }
};
