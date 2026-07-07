<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card comments — sdd/kanban/design §2 (`card_comments`).
 *
 * FK behaviour:
 *   - card_id -> cards.id CASCADE  (deleting a card removes every comment)
 *   - author_id -> users.id SET NULL  (preserves comment text if a user is hard-deleted;
 *     visible as "deleted user")
 *   - parent_id -> card_comments.id SET NULL  (orphan root on parent deletion; thread
 *     becomes a sibling-root cascade in the response)
 *
 * Indexes:
 *   - (card_id, author_id, parent_id) — thread-per-author query shape
 *
 * The `body` column is plain TEXT (NO length cap at the schema layer; the HTTP boundary
 * is enforced at the FormRequest layer — `max:5000`). Comments are CANONICAL TEXT, not
 * Markdown — unlike `cards.body` they are NOT raw-preserved and have no Markdown semantics.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('card_comments')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['card_id', 'author_id', 'parent_id'], 'card_comments_thread_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_comments');
    }
};
