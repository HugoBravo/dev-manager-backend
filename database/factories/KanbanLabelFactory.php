<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KanbanLabel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KanbanLabel>
 */
class KanbanLabelFactory extends Factory
{
    /**
     * The default colour palette. Matches the palette documented in
     * docs/kanban-api.md so generated test data is representative.
     *
     * @var list<string>
     */
    private const PALETTE = [
        '#ef4444', // red
        '#f59e0b', // amber
        '#10b981', // emerald
        '#3b82f6', // blue
        '#8b5cf6', // violet
        '#ec4899', // pink
        '#64748b', // slate
    ];

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(1, true),
            'color' => fake()->randomElement(self::PALETTE),
        ];
    }

    /**
     * Indicate the label belongs to a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
        ]);
    }
}
