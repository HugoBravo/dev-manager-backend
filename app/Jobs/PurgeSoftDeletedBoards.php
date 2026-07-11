<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\KanbanBoard;
use App\Services\Kanban\BoardAuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Daily job that force-deletes `kanban_boards` rows whose `deleted_at` is
 * older than the configured restore window (default 30 days). Runs at
 * 03:00 from `routes/console.php` so it lands outside the request hot
 * path. The audit row for each board is recorded BEFORE the force-delete
 * so the audit trail has a record of the purge; the FK on
 * `board_audit_logs.board_id` is `cascadeOnDelete`, so the row gets
 * removed alongside the board — that's intentional: once a board is
 * force-deleted, its audit trail is deleted too (per the spec, the
 * board-soft-delete window is the only window in which audit can be
 * retrieved; past that, the trail is gone).
 *
 * Concurrency: the job uses `KanbanBoard::query()->chunkById(100, ...)`
 * inside a single pass — for the expected batch size (≤ a few thousand
 * rows/day at this stage) a single pass is plenty. Future batches may
 * introduce a per-batch lock + marker so concurrent runs don't double-
 * purge the same rows.
 */
final class PurgeSoftDeletedBoards implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $purgeAfterDays = (int) config('kanban.purge_after_days', 30);
        $cutoff = now()->subDays($purgeAfterDays);

        $logger = app(BoardAuditLogger::class);

        KanbanBoard::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', $cutoff)
            ->chunkById(100, function ($boards) use ($logger): void {
                foreach ($boards as $board) {
                    DB::transaction(function () use ($board, $logger): void {
                        // Record the purge action BEFORE force-deleting the
                        // board so the audit trail captures the event (it
                        // gets cascaded away with the board FK, which is
                        // intentional per the design brief §3.4).
                        $logger->record($board, 'purged', [
                            'purge_after_days' => (int) config('kanban.purge_after_days', 30),
                        ]);

                        $board->forceDelete();
                    });
                }
            }, 'id', 'id');
    }
}
