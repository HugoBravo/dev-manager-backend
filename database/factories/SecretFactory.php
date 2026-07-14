<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Secret;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Secret>
 */
class SecretFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'key' => strtoupper(fake()->unique()->word()).'_'.fake()->bothify('???'),
            'value' => fake()->sentence(),
            'description' => fake()->optional(0.6)->sentence(),
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn (): array => [
            'project_id' => $project->id,
        ]);
    }
}
