<?php

declare(strict_types=1);

use App\Models\KanbanBoard;
use App\Models\Project;
use App\Models\User;
use App\Policies\KanbanBoardPolicy;
use Tests\TestCase;

it('returns 401 on the bulk endpoints without a bearer token', function (string $method, string $path): void {
    /** @var TestCase $this */
    $response = match ($method) {
        'POST' => $this->postJson($path, ['ids' => [1, 2]]),
    };

    $response->assertUnauthorized();
})->with([
    'bulk-delete' => ['POST', '/api/v1/projects/1/kanban/boards/bulk-delete'],
    'bulk-rename' => ['POST', '/api/v1/projects/1/kanban/boards/bulk-rename'],
]);

it('bulk-deletes all empty boards and returns per-item 204 entry in the results map', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $b1 = KanbanBoard::factory()->forProject($project)->create();
    $b2 = KanbanBoard::factory()->forProject($project)->create();
    $b3 = KanbanBoard::factory()->forProject($project)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/bulk-delete", [
            'ids' => [$b1->id, $b2->id, $b3->id],
        ])
        ->assertOk();

    $results = $response->json('data.results');
    expect($results)->toBeArray()->toHaveCount(3);
    foreach ($results as $entry) {
        expect($entry['status'])->toBe(204);
    }

    $summary = $response->json('data.summary');
    expect($summary['total'])->toBe(3)
        ->and($summary['ok'])->toBe(3)
        ->and($summary['failed'])->toBe(0);

    // Each board is now soft-deleted.
    expect(KanbanBoard::query()->whereNull('deleted_at')->count())->toBe(0);
});

it('bulk-delete returns 409 per item for boards with contents and 404 for foreign ids', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $good = KanbanBoard::factory()->forProject($project)->create();

    // Stub the policy to deny `delete` so the per-item 409 path is exercised.
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
                // Deny ALL deletes to simulate "has contents" semantics.
                return false;
            }
        }
    );

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/bulk-delete", [
            'ids' => [$good->id, 999999],
        ])
        ->assertOk();

    $results = $response->json('data.results');
    expect($results)->toHaveCount(2);

    // First result: 409 board_has_contents.
    expect($results[0]['id'])->toBe($good->id)
        ->and($results[0]['status'])->toBe(409)
        ->and($results[0]['error']['code'])->toBe('board_has_contents');

    // Second result: 404 not_found.
    expect($results[1]['id'])->toBe(999999)
        ->and($results[1]['status'])->toBe(404)
        ->and($results[1]['error']['code'])->toBe('not_found');

    // Nothing got deleted (the policy stub denied it).
    expect(KanbanBoard::query()->whereNull('deleted_at')->count())->toBe(1);
});

it('bulk-rename adds a prefix to all boards', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $b1 = KanbanBoard::factory()->forProject($project)->create(['name' => 'Sprint 1']);
    $b2 = KanbanBoard::factory()->forProject($project)->create(['name' => 'Sprint 2']);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/bulk-rename", [
            'ids' => [$b1->id, $b2->id],
            'prefix' => 'v2-',
            'mode' => 'add',
        ])
        ->assertOk();

    $results = $response->json('data.results');
    expect($results)->toHaveCount(2);
    foreach ($results as $entry) {
        expect($entry['status'])->toBe(200);
    }

    expect($b1->fresh()->name)->toBe('v2-Sprint 1')
        ->and($b2->fresh()->name)->toBe('v2-Sprint 2');
});

it('bulk-rename reports 422 name_taken on the first collision and continues', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $collision = KanbanBoard::factory()->forProject($project)->create(['name' => 'v2-Sprint 2']);
    $b1 = KanbanBoard::factory()->forProject($project)->create(['name' => 'Sprint 1']);
    $b2 = KanbanBoard::factory()->forProject($project)->create(['name' => 'Sprint 2']);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/bulk-rename", [
            'ids' => [$b1->id, $b2->id],
            'prefix' => 'v2-',
            'mode' => 'add',
        ])
        ->assertOk();

    $results = $response->json('data.results');

    // First rename succeeds (no collision).
    expect($results[0]['id'])->toBe($b1->id)
        ->and($results[0]['status'])->toBe(200);

    // Second rename collides with the pre-existing `v2-Sprint 2`.
    expect($results[1]['id'])->toBe($b2->id)
        ->and($results[1]['status'])->toBe(422)
        ->and($results[1]['error']['code'])->toBe('name_taken');

    // First board WAS renamed; second was NOT.
    expect($b1->fresh()->name)->toBe('v2-Sprint 1')
        ->and($b2->fresh()->name)->toBe('Sprint 2');
});

it('rejects 422 max_100 on 101 ids', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/bulk-delete", [
            'ids' => range(1, 101),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ids']);
});

it('rejects 422 on empty ids array', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/bulk-delete", [
            'ids' => [],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ids']);
});

it('bulk-rename remove mode strips the prefix when present', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $b1 = KanbanBoard::factory()->forProject($project)->create(['name' => 'v2-Sprint 1']);
    $b2 = KanbanBoard::factory()->forProject($project)->create(['name' => 'v2-Sprint 2']);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/bulk-rename", [
            'ids' => [$b1->id, $b2->id],
            'prefix' => 'v2-',
            'mode' => 'remove',
        ])
        ->assertOk();

    $results = $response->json('data.results');
    foreach ($results as $entry) {
        expect($entry['status'])->toBe(200);
    }

    expect($b1->fresh()->name)->toBe('Sprint 1')
        ->and($b2->fresh()->name)->toBe('Sprint 2');
});

it('bulk-rename remove mode is no-op (200) when prefix is not present', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $b = KanbanBoard::factory()->forProject($project)->create(['name' => 'Sprint 1']);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/bulk-rename", [
            'ids' => [$b->id],
            'prefix' => 'v2-',
            'mode' => 'remove',
        ])
        ->assertOk();

    $results = $response->json('data.results');
    expect($results)->toHaveCount(1)
        ->and($results[0]['status'])->toBe(200);

    expect($b->fresh()->name)->toBe('Sprint 1');
});

it('bulk-rename rejects 422 when mode is not in add|remove', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $b = KanbanBoard::factory()->forProject($project)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/bulk-rename", [
            'ids' => [$b->id],
            'prefix' => 'v2-',
            'mode' => 'OVERWRITE',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['mode']);
});

it('bulk-rename rejects 422 when prefix is missing or too long', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    // Missing prefix
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/bulk-rename", [
            'ids' => [1],
            'mode' => 'add',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['prefix']);

    // Prefix > 50 chars
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/bulk-rename", [
            'ids' => [1],
            'mode' => 'add',
            'prefix' => str_repeat('a', 51),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['prefix']);
});
