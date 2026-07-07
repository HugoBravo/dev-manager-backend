<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card attachments — sdd/kanban/design §2 (`card_attachments`).
 *
 * FK behaviour:
 *   - card_id -> cards.id CASCADE (deleting a card removes every attachment row)
 *   - uploader_id -> users.id SET NULL (preserves the file/row when a user is hard-deleted;
 *     uploader renders as null in the response — "deleted user")
 *
 * Indexes:
 *   - (card_id) — list attachments of a card
 *
 * The `path` is the storage-relative path on the `local` disk
 * (`storage/app/private/kanban/cards/{card_id}/{uuid}.{ext}`). The frontend
 * will construct the download URL when the download endpoint lands as its
 * own sdd change (out of scope for this batch).
 *
 * Mime and size are stored from the server-side detection — they are NEVER
 * trusted from client-supplied headers. The 5 MB cap and the mime allowlist
 * are enforced at the FormRequest layer before any row is written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path', 512);
            $table->string('original_filename', 255);
            $table->string('mime', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->index('card_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_attachments');
    }
};
