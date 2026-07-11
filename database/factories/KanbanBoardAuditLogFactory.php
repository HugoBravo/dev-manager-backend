<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KanbanBoard;
use App\Models\KanbanBoardAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KanbanBoardAuditLog>
 */
class KanbanBoardAuditLogFactory extends Factory
{
    /**
     * Canonical action strings the audit log understands. New actions should
     * be added here AND to BoardAuditLogger's call sites so the audit panel
     * (frontend) never sees an unknown string.
     *
     * @var list<string>
     */
    public const ACTIONS = [
        'created',
        'archived',
        'unarchived',
        'reordered',
        'cloned',
        'purged',
        'restored',
        'renamed',
    ];

    public function definition(): array
    {
        return [
            'board_id' => KanbanBoard::factory(),
            'actor_user_id' => User::factory(),
            'action' => fake()->randomElement(self::ACTIONS),
            'payload' => [],
        ];
    }

    /**
     * Pin the action for the generated row. Convenience helper for tests
     * that branch on the action string (most do).
     */
    public function withAction(string $action): static
    {
        return $this->state(fn (): array => [
            'action' => $action,
        ]);
    }

    /**
     * Anchor the row to a specific board.
     */
    public function forBoard(KanbanBoard $board): static
    {
        return $this->state(fn (): array => [
            'board_id' => $board->id,
        ]);
    }

    /**
     * Anchor the row to a specific actor.
     */
    public function byActor(User $user): static
    {
        return $this->state(fn (): array => [
            'actor_user_id' => $user->id,
        ]);
    }

    /**
     * Run the row without an acting user (cron / system events).
     */
    public function systemAction(): static
    {
        return $this->state(fn (): array => [
            'actor_user_id' => null,
        ]);
    }
}
