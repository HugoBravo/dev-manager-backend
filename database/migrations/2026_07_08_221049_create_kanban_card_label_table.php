<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot `kanban_card_label` — many-to-many between cards and labels.
 *
 * Cascade on both FKs: when a card is hard-deleted the controller runs
 * the card deletion inside a DB transaction and the FK removes the
 * pivot rows; the same applies when a label is deleted. We don't rely
 * on application-level cleanup for pivot rows.
 *
 * A composite PK on (card_id, label_id) replaces the surrogate `id`
 * column because the pair IS the identity. The (label_id) secondary
 * index supports reverse lookups ("which cards use this label?").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_card_label', function (Blueprint $table): void {
            $table->foreignId('card_id')->constrained('kanban_cards')->cascadeOnDelete();
            $table->foreignId('label_id')->constrained('kanban_labels')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['card_id', 'label_id']);
            $table->index('label_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_card_label');
    }
};
