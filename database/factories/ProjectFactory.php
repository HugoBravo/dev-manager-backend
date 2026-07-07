<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
        ];
    }

    /**
     * Indicate that the project belongs to a specific owner.
     * Used by tests with `Project::factory()->for($owner)->create()`.
     */
    public function forOwner(User $owner): static
    {
        return $this->state(fn (): array => [
            'owner_id' => $owner->id,
        ]);
    }
}
