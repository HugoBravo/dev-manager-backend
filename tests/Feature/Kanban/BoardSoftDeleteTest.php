<?php

declare(strict_types=1);

use App\Models\KanbanBoard;
use App\Models\Project;
use App\Models\User;
use App\Policies\KanbanBoardPolicy;

it('soft-deletes an empty board and excludes it from default index', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('kanban_boards', ['id' => $board->id]);

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards")
        ->assertOk();

    $ids = collect($response->json('data'))
        ->map(fn (array $envelope): mixed => $envelope['data']['id'] ?? null)
        ->all();

    expect($ids)->not->toContain($board->id);
});

it('returns 409 board_has_contents when soft-deleting a non-empty board', function (): void {
    // The 409 path is exercised today by stubbing the policy to deny delete()
    // — this is how BoardTest covers it. After SoftDeletes ships, the same
    // 409 path must still fire BEFORE the row's deleted_at is touched, so
    // the assertion that deleted_at is null is the contract we lock in here.
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $this->app->bind(
        KanbanBoardPolicy::class,
        fn () => new class
        {
            public function create(User $user): bool
            {
                return true;
            }

            public function delete(User $user, KanbanBoard $board): bool
            {
                return false;
            }
        }
    );

    $response = $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}")
        ->assertStatus(409);

    expect($response->json('code'))->toBe('board_has_contents');

    // Soft-delete must not have touched the row: the 409 path throws before
    // `$board->delete()` runs, so `deleted_at` stays null on the row.
    expect($board->fresh()->deleted_at)->toBeNull();
});
