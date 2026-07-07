<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kanban\Concerns;

use App\Models\KanbanCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * CONTROLLING CASCADE: Attachment file deletion is performed EXPLICITLY
 * by the controllers that own the cascade path. Do NOT add a `KanbanAttachment`
 * or `KanbanCard` model observer that auto-deletes files — it will either
 * double-delete (files gone but row not) or skip cleanup (rows gone but
 * disk leaks). Future contributors must extend this trait instead of
 * adding observers.
 *
 * Why controller-led and not observer-led:
 *   - Observability: a small finite set of cascade paths (card destroy,
 *     attachment destroy, future project destroy). Centralized here.
 *   - Test isolation: Pest feature tests can assert on filesystem state
 *     without subscribing to observer events.
 *   - Transaction boundary: the file delete runs INSIDE the same
 *     transaction as the DB delete so a failed rollback doesn't leave
 *     a dangling file. Observers run outside the transaction by default.
 *
 * Storage layout: `storage/app/private/kanban/cards/{card_id}/{uuid}.{ext}`
 * — under `private` so it's never web-accessible. The `local` disk driver
 * already writes to `storage/app/private/` by default in Laravel 11+.
 */
trait CascadesKanbanCardFiles
{
    /**
     * Hard-delete a card AND its attachment files atomically.
     *
     * Sequence (in order):
     *   1. Snapshot every `kanban_attachments.path` BEFORE the row delete.
     *   2. `$card->delete()` — DB-level FK CASCADE removes
     *      `kanban_comments` AND `kanban_attachments` rows.
     *   3. `Storage::disk('local')->delete($paths)` for the snapshot.
     *   4. Wrap steps 1-3 in `DB::transaction` so a thrown file delete
     *      rolls back the row deletion (test: `rolls_back_the_card_row_deletion...`).
     *
     * Failure semantics: if `Storage::delete()` throws, the throw bubbles
     * out of the closure, `DB::transaction` rolls back the row deletion,
     * and the caller receives a 500 — the card and its attachment rows
     * are preserved. This is the atomicity contract.
     */
    protected function deleteCardWithFileCascade(KanbanCard $card): void
    {
        DB::transaction(function () use ($card): void {
            // Snapshot BEFORE the row delete — once FK CASCADE removes
            // the rows, we can no longer read their `path` column.
            $paths = $card->attachments()->pluck('path')->all();

            $card->delete();

            // Let the throw bubble — DB::transaction rolls back the
            // row deletion, the response becomes 500, the client can
            // retry. We do NOT swallow on cascade (unlike the standalone
            // `Kanban\AttachmentController::destroy` path, which deletes the file
            // BEFORE the row and has a different failure mode).
            foreach ($paths as $path) {
                Storage::disk('local')->delete($path);
            }
        });
    }
}
