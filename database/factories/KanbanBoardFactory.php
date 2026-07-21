<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KanbanBoard;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;

/**
 * @extends Factory<KanbanBoard>
 */
class KanbanBoardFactory extends Factory
{
    /**
     * The default position fraction seeded when a board is created without one.
     *
     * In Batch 2 we seed boards with monotonically-increasing positions so the
     * factory yields an ordered list for the index/listing tests. Batch 3 ships
     * the `App\ValueObjects\Kanban\Position` value object which replaces this naive
     * generator with a fractional-indexing `append()`/`between()`. The factory's
     * `position` override accepts arbitrary strings — tests assert observable
     * behaviour (ordering, dup detection, cap), never raw string shape.
     */
    private static int $seedCounter = 0;

    public function configure(): static
    {
        return $this->afterMaking(function (KanbanBoard $board): void {
            if (! Schema::hasColumn('kanban_boards', 'task_id') || $board->task_id !== null) {
                return;
            }

            $task = Task::query()->firstOrCreate(
                [
                    'project_id' => $board->project_id,
                    'slug' => 'default',
                ],
                [
                    'name' => 'Default',
                    'status' => 'open',
                ],
            );

            $board->task_id = $task->id;
        });
    }

    public function definition(): array
    {
        $seed = self::$seedCounter++;
        // 'a' + base_convert(>0 -> 36) — fixed-width and lexicographically
        // sortable across all integer values up to PHP_INT_MAX.
        $lex = base_convert((string) (10 + $seed), 10, 36);

        return [
            'project_id' => Project::factory(),
            // Unique-per-factory-call name so the Batch 1.5 case-insensitive
            // unique index `(project_id, LOWER(name)) WHERE deleted_at IS NULL`
            // does not fire spuriously under test seeding.
            'name' => 'Board '.uniqid('b'),
            'position' => 'a'.$lex,
            'archived_at' => null,
        ];
    }

    /**
     * Indicate the board belongs to a specific task.
     */
    public function forTask(Task $task): static
    {
        return $this->state(fn (): array => [
            'project_id' => $task->project_id,
            'task_id' => $task->id,
        ]);
    }

    /**
     * Indicate the board belongs to a specific project.
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (): array => [
            'project_id' => $project->id,
        ]);
    }

    /**
     * Mark the board as archived.
     */
    public function archived(): static
    {
        return $this->state(fn (): array => [
            'archived_at' => now(),
        ]);
    }
}
