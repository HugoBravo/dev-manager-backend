<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KanbanBoard;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'project_id' => Project::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'status' => 'open',
            'priority' => 'MEDIUM',
            'archived_at' => null,
        ];
    }

    public function high(): static
    {
        return $this->state(fn (): array => [
            'priority' => 'HIGH',
        ]);
    }

    public function low(): static
    {
        return $this->state(fn (): array => [
            'priority' => 'LOW',
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'archived_at' => now(),
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Default',
            'slug' => 'default',
        ]);
    }

    public function withBoards(int $count = 1): static
    {
        return $this->afterCreating(function (Task $task) use ($count): void {
            $attributes = Schema::hasColumn('kanban_boards', 'task_id')
                ? ['task_id' => $task->id]
                : ['project_id' => $task->project_id];

            KanbanBoard::factory()->count($count)->create($attributes);
        });
    }
}
