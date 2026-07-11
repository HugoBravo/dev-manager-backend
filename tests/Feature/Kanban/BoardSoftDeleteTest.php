<?php

declare(strict_types=1);

use App\Models\KanbanBoard;
use App\Models\Project;
use App\Models\User;
use App\Policies\KanbanBoardPolicy;
use Tests\TestCase;

it('returns 401 on every soft-delete / restore / trash endpoint without a bearer token', function (string $method, string $path): void {
    /** @var TestCase $this */
    $response = match ($method) {
        'GET' => $this->getJson($path),
        'POST' => $this->postJson($path, []),
    };

    $response->assertUnauthorized();
})->with([
    'restore' => ['POST', '/api/v1/projects/1/kanban/boards/1/restore'],
    'trashed' => ['GET', '/api/v1/projects/1/kanban/boards/trashed'],
]);

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

it('restores a soft-deleted board within the restore window', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    // Trashed first so we exercise the restore path.
    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}")
        ->assertNoContent();
    $this->assertSoftDeleted('kanban_boards', ['id' => $board->id]);

    // Restore via the explicit endpoint.
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.id', $board->id)
        ->assertJsonPath('data.name', $board->name);

    // Row must no longer be soft-deleted.
    $fresh = $board->fresh();
    expect($fresh->deleted_at)->toBeNull();

    // Default index now contains it again (excludes trashed by default).
    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards")
        ->assertOk();

    $ids = collect($response->json('data'))
        ->map(fn (array $envelope): mixed => $envelope['data']['id'] ?? null)
        ->all();

    expect($ids)->toContain($board->id);
});

it('returns 422 not_trashed when restoring an active board', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/restore")
        ->assertStatus(422);

    expect($response->json('code'))->toBe('not_trashed');
});

it('lists trashed boards paginated newest-first', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    // Three boards trashed at controlled timestamps so deleted_at order is fixed.
    $old = KanbanBoard::factory()->for($project, 'project')->create();
    $mid = KanbanBoard::factory()->for($project, 'project')->create();
    $new = KanbanBoard::factory()->for($project, 'project')->create();
    // One active board that must NOT appear in trashed.
    $active = KanbanBoard::factory()->for($project, 'project')->create();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$old->id}")
        ->assertNoContent();
    $old->fresh()->deleted_at = now()->subDays(3);
    // Manual write to keep the deleted_at ordering deterministic.
    DB::table('kanban_boards')->where('id', $old->id)->update(['deleted_at' => now()->subDays(3)]);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$mid->id}")
        ->assertNoContent();
    DB::table('kanban_boards')->where('id', $mid->id)->update(['deleted_at' => now()->subDays(2)]);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$new->id}")
        ->assertNoContent();
    DB::table('kanban_boards')->where('id', $new->id)->update(['deleted_at' => now()->subDay()]);

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/trashed")
        ->assertOk();

    $ids = collect($response->json('data'))
        ->map(fn (array $envelope): mixed => $envelope['data']['id'] ?? null)
        ->all();

    // Newest-first: new, mid, old. Active must not be listed.
    expect($ids)->toBe([$new->id, $mid->id, $old->id])
        ->and($ids)->not->toContain($active->id);
});
