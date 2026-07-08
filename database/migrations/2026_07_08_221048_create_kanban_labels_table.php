<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kanban labels — user-scoped taxonomy applied to cards.
 *
 * A label belongs to exactly one user (the owner). It can be assigned to
 * many cards across the user's projects via the `kanban_card_label` pivot.
 * The `unique(user_id, name)` index prevents the same user from creating
 * two labels with the same name; it does NOT prevent two users from
 * having a label called "bug" because the contract is global-per-user.
 *
 * `color` is stored as a 7-char hex string (`#RRGGBB`). The FormRequest
 * layer enforces that exact shape; the migration does not constrain it
 * to keep the schema portable if a future change wants to add rgba / hsl.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_labels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 64);
            $table->string('color', 7);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_labels');
    }
};
