<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KanbanColumn>
 */
class KanbanColumnFactory extends Factory
{
    /**
     * The default position fraction seeded when a column is created without one.
     *
     * Mirrors BoardFactory's seed strategy: a stable, monotonically-growing
     * lexicographic prefix ('a' + base-36 of $seed) so factory builds yield
     * a stable insertion order for index/listing tests. The Position value
     * object ships in Batch 3 and is used by the controller's store +
     * reorder endpoints; the factory keeps a self-contained seed because
     * SeedStr is easier for tests that just need a valid VARCHAR column.
     */
    private static int $seedCounter = 0;

    public function definition(): array
    {
        $seed = self::$seedCounter++;
        // base_convert >0 -> base 36 yields a fixed-width, lexicographically
        // sortable suffix for every positive integer seed value.
        $lex = base_convert((string) (10 + $seed), 10, 36);

        return [
            'board_id' => KanbanBoard::factory(),
            'name' => fake()->words(2, true),
            'position' => 'a'.$lex,
            'archived_at' => null,
        ];
    }

    /**
     * Indicate the column belongs to a specific board.
     */
    public function forBoard(KanbanBoard $board): static
    {
        return $this->state(fn (): array => [
            'board_id' => $board->id,
        ]);
    }
}
