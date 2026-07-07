<?php

declare(strict_types=1);

namespace App\Http\Resources\Kanban;

use App\Models\KanbanAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * KanbanAttachment resource shape (Batch 6 brief):
 *   { data: { id, card_id, uploader_id, original_filename, mime,
 *             size_bytes, url, created_at } }
 *
 * `url` is a placeholder string — it does NOT hit the disk. The frontend
 * constructs the actual download URL from the resource shape once the
 * download endpoint lands as its own sdd change (out of scope for this
 * batch). Keeping the key in the envelope makes the schema stable.
 *
 * @mixin KanbanAttachment
 */
final class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'id' => $this->resource->id,
                'card_id' => $this->resource->card_id,
                'uploader_id' => $this->resource->uploader_id,
                'original_filename' => $this->resource->original_filename,
                'mime' => $this->resource->mime,
                'size_bytes' => $this->resource->size_bytes,
                'url' => $this->resource->path
                    ? "/api/v1/cards/{$this->resource->card_id}/attachments/{$this->resource->id}/download"
                    : null,
                'created_at' => optional($this->resource->created_at)?->toIso8601String(),
            ],
        ];
    }
}
