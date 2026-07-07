<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * KanbanAttachment — a single file attached to a card. Lives on the `local`
 * disk under `storage/app/private/kanban/cards/{card_id}/{uuid}.{ext}`.
 *
 * The cascade-delete contract is controller-led (NOT a model observer — see
 * `App\Http\Controllers\Api\V1\Kanban\Concerns\CascadesKanbanCardFiles`). When a card
 * is hard-deleted, the controller snapshots attachment paths, deletes the
 * card (which cascades the rows away via FK), then calls
 * `Storage::disk('local')->delete()` for each path — wrapped in
 * `DB::transaction` so a failed file delete rolls back the row deletion.
 *
 * `uploader_id` is nullable: a hard-deleted user preserves the file and row
 * with uploader_id NULL.
 *
 * @mixin Builder
 */
#[Fillable(['card_id', 'uploader_id', 'disk', 'path', 'original_filename', 'mime', 'size_bytes'])]
class KanbanAttachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    protected $table = 'kanban_attachments';

    /**
     * The card this attachment belongs to.
     *
     * @return BelongsTo<KanbanCard, self>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(KanbanCard::class, 'card_id');
    }

    /**
     * The user who uploaded the attachment. Nullable — a hard-deleted
     * user leaves the row with uploader_id NULL.
     *
     * @return BelongsTo<User, self>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }
}
