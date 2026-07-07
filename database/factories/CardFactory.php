<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Card;
use App\Models\KanbanColumn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    /**
     * Stable seed counter — same pattern as BoardFactory / KanbanColumnFactory
     * so factory builds yield a stable lexicographic insertion order for
     * index/reorder tests. Position is assigned by the controller via the
     * `Position` value object at runtime; the factory seed is a fallback so
     * tests that just need a valid row don't depend on the controller path.
     */
    private static int $seedCounter = 0;

    public function definition(): array
    {
        $seed = self::$seedCounter++;
        $lex = base_convert((string) (10 + $seed), 10, 36);

        return [
            'column_id' => KanbanColumn::factory(),
            'title' => fake()->sentence(3),
            'body' => null,
            'position' => 'a'.$lex,
            'due_date' => null,
            'archived_at' => null,
        ];
    }

    /**
     * Indicate the card belongs to a specific column.
     */
    public function forColumn(KanbanColumn $column): static
    {
        return $this->state(fn (): array => [
            'column_id' => $column->id,
        ]);
    }

    /**
     * Mark the card as archived (sets archived_at to now).
     */
    public function archived(): static
    {
        return $this->state(fn (): array => [
            'archived_at' => now(),
        ]);
    }

    /**
     * Attach a Markdown body to the factory state (used by body-shape tests).
     */
    public function withBody(string $body): static
    {
        return $this->state(fn (): array => [
            'body' => $body,
        ]);
    }
}
