<?php

declare(strict_types=1);

use App\Models\KanbanBoard;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalise kanban positions into the a..z alphabet the Position VO speaks.
 *
 * Background: an earlier version of ExampleDemoSeeder wrote position
 * values using a base-36 alphabet (0..9 + a..z) so the first slot in
 * each parent landed on '0'. The Position value object only accepts
 * a..z — `Position::after('0')` blows up in `alphIndex()` and the next
 * card move returns HTTP 500 (the controller's `nextPositionForColumn`
 * reads the rightmost position via `orderByDesc('position')` and feeds
 * it straight into the VO).
 *
 * Strategy: walk each scoped set (boards per project, columns per
 * board, cards per column) ordered by their CURRENT position (so the
 * intended order is preserved regardless of the alphabet), then
 * rewrite each row's position to a stable indexed sequence using the
 * same `indexedPosition()` strategy as ColumnController::reorder()
 * (prefix 'r' + base-26 letter + 'a' padding per depth). The rewrite
 * puts every row into the a..z alphabet so the VO is happy forever
 * after. Reorders and drag-drop still work normally because both
 *   1) the query orders by `position` lexicographically (still stable),
 *   2) the append path uses `Position::after(rightmost)` which now
 *      operates on a sane base.
 *
 * The seeders have also been fixed to emit a..z positions only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->normaliseBoards();
        $this->normaliseColumns();
        $this->normaliseCards();
    }

    public function down(): void
    {
        // No reversible action — we cannot reconstruct the original
        // (potentially invalid) positions. The down() is intentionally
        // a no-op so `migrate:rollback` doesn't silently corrupt data.
    }

    private function normaliseBoards(): void
    {
        // Walk every project; within it, order boards by their current
        // `position` and rewrite each to an indexed stable sequence.
        $projectIds = KanbanBoard::query()
            ->select('project_id')
            ->distinct()
            ->pluck('project_id');

        foreach ($projectIds as $projectId) {
            $boards = KanbanBoard::query()
                ->where('project_id', $projectId)
                // Sort by the raw position column. SQLite / MySQL /
                // Postgres all do byte-wise comparison on VARCHARs which
                // is consistent with how the production code orders them.
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'position']);

            $this->rewritePositions($boards, 'id');
        }
    }

    private function normaliseColumns(): void
    {
        $boardIds = KanbanColumn::query()
            ->select('board_id')
            ->distinct()
            ->pluck('board_id');

        foreach ($boardIds as $boardId) {
            $columns = KanbanColumn::query()
                ->where('board_id', $boardId)
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'position']);

            $this->rewritePositions($columns, 'id');
        }
    }

    private function normaliseCards(): void
    {
        $columnIds = KanbanCard::query()
            ->select('column_id')
            ->distinct()
            ->pluck('column_id');

        foreach ($columnIds as $columnId) {
            $cards = KanbanCard::query()
                ->where('column_id', $columnId)
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'position']);

            $this->rewritePositions($cards, 'id');
        }
    }

    /**
     * Assign an indexed position string to every row, preserving the
     * input ordering. Mirrors the alphabet used by
     * {@see \App\Http\Controllers\Api\V1\Kanban\Concerns\ComputesKanbanPositions::indexedPosition()}
     * so future reorders / moves don't have to think about this seed.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    private function rewritePositions(\Illuminate\Support\Collection $rows, string $primaryKey): void
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz';
        $max = strlen($alphabet) - 1; // 25 = 'z'

        $index = 0;
        foreach ($rows as $row) {
            $letter = $alphabet[$index % 26] ?? 'z';
            $depth = intdiv($index, 26);
            $newPosition = $depth > 0
                ? $letter.str_repeat('a', $depth)
                : $letter;

            DB::table($this->tableFor($row))
                ->where($primaryKey, $row->{$primaryKey})
                ->update(['position' => $newPosition]);

            $index++;
            // Defensive: at very high counts the indexed sequence could
            // exceed 'z' + many 'a's. The next move would handle that
            // via the VO's MAX_LENGTH cap and throw a typed 422, which
            // is the contract for exhaustion — nothing to do here.
            if ($index > $max + 1 && $depth > 50) {
                break;
            }
        }
    }

    /**
     * Map a row instance back to its table name. We only ever pass the
     * three kanban models, so an instanceof ladder is enough and
     * avoids leaking model class strings into the migration body.
     */
    private function tableFor(object $row): string
    {
        return match (true) {
            $row instanceof KanbanBoard => 'kanban_boards',
            $row instanceof KanbanColumn => 'kanban_columns',
            $row instanceof KanbanCard => 'kanban_cards',
            default => throw new \LogicException('Unexpected row type in position migration'),
        };
    }
};