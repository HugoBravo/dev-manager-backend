<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesKanbanChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Board;
use App\Models\Card;
use App\Models\CardAttachment;
use App\Models\KanbanColumn;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * AttachmentController — card attachments under
 * /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards/{card}/attachments
 *
 * Authorization:
 *  - 404-not-403 cross-owner via `Route::bind('attachment', ...)` (AppServiceProvider)
 *    + the controller's `ensureCardBelongsToColumn` belt-and-braces.
 *  - 401 unauthenticated via `auth:sanctum`.
 *
 * Storage layout:
 *   - disk: `local` (config/filesystems.php — `storage/app/private`)
 *   - path: `kanban/cards/{card_id}/{uuid}.{ext}`
 *
 * Cascade contract:
 *   - Attachment deletion: this controller removes the file FIRST, then
 *     the row. Disk failures bubble up as a 500 (rare).
 *   - Card deletion: handled by `CardController::destroy` via the
 *     `CascadesCardFiles` trait — files are deleted AFTER the row delete
 *     (because FK CASCADE removes the rows first), inside a transaction.
 *
 * R1 (Batch 7): every action respects the project-level `archived_at`
 * via the `KanbanRequestScope` helper exposed by `ResolvesKanbanChain`.
 */
final class AttachmentController extends Controller
{
    use ResolvesKanbanChain;

    /**
     * List attachments of a card. Pagination page[size]=25 per the spec.
     * R1: archived project → empty list unless `?include_archived=1`.
     */
    public function index(Request $request, int $project, Board $board, KanbanColumn $column, Card $card): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);

        if (! $this->includeArchived($request) && $projectModel->archived_at !== null) {
            return AttachmentResource::collection(
                CardAttachment::query()->whereRaw('1 = 0')->paginate(25)
            )->response();
        }

        $attachments = CardAttachment::query()
            ->where('card_id', $card->id)
            ->orderBy('id')
            ->paginate(25);

        return AttachmentResource::collection($attachments)->response();
    }

    /**
     * Upload a single attachment. `StoreAttachmentRequest` enforces the
     * mime allowlist + 5 MB cap BEFORE any disk write; we trust its
     * output here.
     *
     * Sequence:
     *   1. Resolve ownership (404 cross-owner via the chain helpers).
     *   2. Authorize `create` on the AttachmentPolicy.
     *   3. Build a uuid-prefixed filename (`{uuid}.{ext}`).
     *   4. `Storage::disk('local')->putFileAs('kanban/cards/{card_id}', $file, $name)`
     *      — this returns the storage-relative path.
     *   5. DB insert inside `DB::transaction`. If the insert throws, the
     *      `catch` deletes the file to avoid orphan disk writes.
     *   6. 201 + `AttachmentResource`.
     * R1: archived project → 404 unless `?include_archived=1`.
     */
    public function store(StoreAttachmentRequest $request, int $project, Board $board, KanbanColumn $column, Card $card): JsonResponse
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);
        $this->authorize('create', CardAttachment::class);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $generatedName = Str::uuid()->toString().'.'.$extension;
        $directory = "kanban/cards/{$card->id}";

        try {
            $path = DB::transaction(function () use ($file, $directory, $generatedName, $card, $request): string {
                $stored = Storage::disk('local')->putFileAs($directory, $file, $generatedName);

                if ($stored === false) {
                    throw new \RuntimeException('Storage::putFileAs returned false.');
                }

                $attachment = CardAttachment::query()->create([
                    'card_id' => $card->id,
                    'uploader_id' => $request->user()->id,
                    'disk' => 'local',
                    'path' => $stored,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType() ?? 'application/octet-stream',
                    'size_bytes' => $file->getSize() ?: 0,
                ]);

                return $attachment->path;
            });
        } catch (\Throwable $e) {
            // DB insert failed — remove the file we just wrote.
            Storage::disk('local')->delete($directory.'/'.$generatedName);
            throw $e;
        }

        $attachment = CardAttachment::query()->where('path', $path)->firstOrFail();

        return (new AttachmentResource($attachment))->response()->setStatusCode(201);
    }

    /**
     * Hard-delete an attachment. The file is removed from disk FIRST;
     * if `Storage::delete()` throws, the row stays intact and the caller
     * can retry. Then the row is deleted inside a `DB::transaction` so
     * the response is atomic at the HTTP boundary.
     * R1: archived project → 404 unless `?include_archived=1`.
     */
    public function destroy(Request $request, int $project, Board $board, KanbanColumn $column, Card $card, CardAttachment $attachment): Response
    {
        $projectModel = $this->resolveOwnedProject($request, $project);
        $this->ensureBoardBelongsToProject($board, $projectModel);
        $this->ensureColumnBelongsToBoard($column, $board);
        $this->ensureCardBelongsToColumn($card, $column);
        $this->ensureNotArchivedProject($request, $projectModel, Project::class, $project);

        // Cross-card guard — the binding closure enforces ownership but
        // not URL-vs-relationship consistency.
        if ($attachment->card_id !== $card->id) {
            abort(404);
        }

        $this->authorize('delete', $attachment);

        DB::transaction(function () use ($attachment): void {
            Storage::disk('local')->delete($attachment->path);
            $attachment->delete();
        });

        return response()->noContent();
    }
}
