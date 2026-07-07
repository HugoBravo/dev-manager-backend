<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kanban\Concerns;

use App\Models\KanbanCard;
use App\ValueObjects\Kanban\Position;

/**
 * Position string helpers shared by controllers that assign KanbanCard /
 * KanbanColumn / KanbanBoard fraction positions. Two strategies:
 *
 *   - `nextPositionFor*()` for **append** — uses the `Position` value object's
 *     `after()` to extend the rightmost fraction in the column/board.
 *
 *   - `indexedPosition()` for **bulk reorder** — returns a stable indexed
 *     string (`r` + base-26 letter + optional `a`-padding) so a second fetch
 *     yields identical order. Deliberately avoids `Position::between` so
 *     reorder is O(1) per write and never triggers precision exhaustion.
 */
trait ComputesKanbanPositions
{
    /**
     * Next position to append under a column via the `Position` value object.
     */
    private function nextPositionForColumn(int $columnId): string
    {
        $rightmost = KanbanCard::query()
            ->where('column_id', $columnId)
            ->orderByDesc('position')
            ->value('position');

        if ($rightmost === null) {
            return Position::start()->value();
        }

        return Position::after($rightmost)->value();
    }

    /**
     * Indexed position string for the Nth slot of a reordered batch.
     */
    private function indexedPosition(int $index): string
    {
        $prefix = 'r';
        $letter = chr(ord('a') + ($index % 26));
        $depth = intdiv($index, 26);

        $base = $prefix.$letter;

        return $depth > 0 ? $base.str_repeat('a', $depth) : $base;
    }
}
