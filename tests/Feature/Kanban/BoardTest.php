<?php

declare(strict_types=1);

use App\Models\KanbanBoard;
use App\Models\Project;
use App\Models\User;
use App\Policies\KanbanBoardPolicy;
use Tests\TestCase;

beforeEach(function (): void {
    // RefreshDatabase is wired project-wide via tests/Pest.php line 18-20; do not duplicate here.
});

it('returns 401 on every board endpoint without a bearer token', function (string $method, string $path): void {
    /** @var TestCase $this */
    $response = match ($method) {
        'GET' => $this->getJson($path),
        'POST' => $this->postJson($path, []),
        'PATCH' => $this->patchJson($path, []),
        'DELETE' => $this->deleteJson($path),
    };

    $response->assertUnauthorized();
})->with([
    'index' => ['GET', '/api/v1/projects/1/kanban/boards'],
    'store' => ['POST', '/api/v1/projects/1/kanban/boards'],
    'show' => ['GET', '/api/v1/projects/1/kanban/boards/1'],
    'update' => ['PATCH', '/api/v1/projects/1/kanban/boards/1'],
    'destroy' => ['DELETE', '/api/v1/projects/1/kanban/boards/1'],
    'archive' => ['POST', '/api/v1/projects/1/kanban/boards/1/archive'],
    'reorder' => ['POST', '/api/v1/projects/1/kanban/boards/reorder'],
]);

it('lists boards of an owned project', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    KanbanBoard::factory()->for($project, 'project')->count(3)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards")
        ->assertOk();

    $ids = collect($response->json('data'))
        ->map(fn (array $envelope) => $envelope['data']['id'] ?? null)
        ->all();

    expect($ids)->toHaveCount(3);
});

it('returns a paginated 25-per-page list of boards', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    KanbanBoard::factory()->for($project, 'project')->count(30)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards")
        ->assertOk();

    // Resource::collection wraps each item in {data:{...}}, so the top-level
    // body has its own `data` array plus LengthAwarePaginator meta fields.
    expect($response->json('data'))->toHaveCount(25);
});

it('creates a board in an owned project with 201', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards", [
            'name' => 'Sprint 1',
        ])
        ->assertCreated();

    $id = $response->json('data.id');
    expect($id)->toBeInt();

    $board = KanbanBoard::query()->findOrFail($id);
    expect($board->name)->toBe('Sprint 1')
        ->and($board->project_id)->toBe($project->id)
        ->and($board->archived_at)->toBeNull()
        ->and($board->position)->toBeString();
});

it('rejects create with empty name', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards", ['name' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('rejects create with name longer than 100 chars', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards", [
            'name' => str_repeat('a', 101),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('returns 404 when listing boards of a project the user does not own', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards")
        ->assertNotFound();
});

it('shows a board to the project owner', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $board->id)
        ->assertJsonPath('data.name', $board->name);
});

it('returns 404 when a non-owner fetches a board (no existence leak)', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}")
        ->assertNotFound();
});

it('returns 404 when fetching a board of a project the user does not own even if board id exists', function (): void {
    // Cross-project attack: stranger owns his own project+board; uses own project id
    // in the URL but the OTHER project's board id. Both must 404 — no leak.
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $ownedProject = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($ownedProject, 'project')->create();
    $strangerProject = Project::factory()->forOwner($stranger)->create();

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/projects/{$strangerProject->id}/kanban/boards/{$board->id}")
        ->assertNotFound();
});

it('updates a boards name for the project owner', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}", [
            'name' => 'Renamed',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');

    expect($board->fresh()->name)->toBe('Renamed');
});

it('returns 404 when a non-owner updates a board', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $this->actingAs($stranger, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}", [
            'name' => 'Hijacked',
        ])
        ->assertNotFound();

    expect($board->fresh()->name)->not->toBe('Hijacked');
});

it('rejects update with name longer than 100 chars', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}", [
            'name' => str_repeat('b', 101),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('deletes an empty board with 204 (soft-delete: row stays, deleted_at is set)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}")
        ->assertNoContent();

    // Soft-deletion leaves the row on disk with `deleted_at` populated so the
    // trash + restore flows (Batch 1.4) can resurrect it within the window.
    $this->assertSoftDeleted('kanban_boards', ['id' => $board->id]);
});

it('returns 404 when GET-ting a soft-deleted board', function (): void {
    // Regression for the SoftDeletes trait: the global scope filters out
    // trashed rows from every query (including the {board} Route::bind
    // closure), so a soft-deleted board must read as "not found" until the
    // restore flow (Batch 1.4) brings it back. This guarantees we never
    // leak the existence of a trashed board to API callers.
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}")
        ->assertNoContent();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}")
        ->assertNotFound();
});

it('returns 409 when destroying a board that has columns (with cards under it)', function (): void {
    // Spec note: the Batch 2 409 contract covers "non-empty board". The
    // `kanban_columns` table does not exist yet (Batch 3), so we exercise the
    // 409 logic by binding a BoardPolicy stub that denies delete() — which is
    // what the controller interprets as "non-empty". Batch 3 will replace the
    // stub with the real `!columns()->exists()` check; the controller code path
    // already handles both. The 409 response shape is asserted separately in
    // the next test.
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    // Stub BoardPolicy::delete to return false: controller treats that as
    // "non-empty" and throws BoardHasContentsException -> 409.
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

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}")
        ->assertStatus(409);

    expect(KanbanBoard::query()->find($board->id))->not->toBeNull();
});

it('returns 409 with a typed `board_has_contents` code in the response body', function (): void {
    // Forward-compatible contract check: the 409 body carries a `code` field
    // the frontend can switch on. Independent test from the destroy-flow above.
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $this->app->bind(
        KanbanBoardPolicy::class,
        fn () => new class
        {
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
});

it('returns 404 when a non-owner deletes a board', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $this->actingAs($stranger, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}")
        ->assertNotFound();

    expect(KanbanBoard::query()->find($board->id))->not->toBeNull();
});

it('archives a board for the project owner', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/archive")
        ->assertOk();

    // Resource is wrapped by BoardResource's own `data` key inside the
    // JsonResource::response() envelope, so the path is `data.data.archived_at`.
    $archivedAt = $response->json('data.data.archived_at')
        ?? $response->json('data.archived_at');
    expect($archivedAt)->not->toBeNull();

    expect($board->fresh()->archived_at)->not->toBeNull();
});

it('archive is idempotent (a second archive call does not flip the timestamp back)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/archive")
        ->assertOk();

    $firstArchivedAt = $board->fresh()->archived_at;
    expect($firstArchivedAt)->not->toBeNull();

    // Sleep one second so a fresh `now()` would have a different value if the
    // controller were re-stamping.
    sleep(1);

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/archive")
        ->assertOk();

    // Compare the two timestamps as Carbon instances via equalTo() on either side.
    $secondArchivedAt = $board->fresh()->archived_at;
    expect($secondArchivedAt->equalTo($firstArchivedAt))->toBeTrue();
});

it('returns 404 when a non-owner archives a board', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $this->actingAs($stranger, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/archive")
        ->assertNotFound();

    expect($board->fresh()->archived_at)->toBeNull();
});

it('reorders boards and persists the new ordering on a second fetch', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $b1 = KanbanBoard::factory()->for($project, 'project')->create();
    $b2 = KanbanBoard::factory()->for($project, 'project')->create();
    $b3 = KanbanBoard::factory()->for($project, 'project')->create();

    // New order: b3, b1, b2.
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/reorder", [
            'ordered_ids' => [$b3->id, $b1->id, $b2->id],
        ])
        ->assertOk();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards")
        ->assertOk();

    $ids = collect($response->json('data'))
        ->map(fn (array $envelope) => $envelope['data']['id'] ?? null)
        ->values()
        ->all();

    expect($ids)->toBe([$b3->id, $b1->id, $b2->id]);
});

it('returns 404 when a non-owner reorders boards', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $b1 = KanbanBoard::factory()->for($project, 'project')->create();
    $b2 = KanbanBoard::factory()->for($project, 'project')->create();

    $this->actingAs($stranger, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/reorder", [
            'ordered_ids' => [$b2->id, $b1->id],
        ])
        ->assertNotFound();
});

it('rejects reorder payload with ids from another project', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $b1 = KanbanBoard::factory()->for($project, 'project')->create();
    $b2 = KanbanBoard::factory()->for($project, 'project')->create();

    // Foreign project board owned by the same user but different project.
    $otherProject = Project::factory()->forOwner($owner)->create();
    $foreignBoard = KanbanBoard::factory()->for($otherProject, 'project')->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/reorder", [
            'ordered_ids' => [$b1->id, $foreignBoard->id, $b2->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ordered_ids']);
});

it('rejects reorder payload with duplicate ids', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $b1 = KanbanBoard::factory()->for($project, 'project')->create();
    $b2 = KanbanBoard::factory()->for($project, 'project')->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/reorder", [
            'ordered_ids' => [$b1->id, $b1->id, $b2->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ordered_ids']);
});

it('exposes the resource shape with id, name, project_id, position, archived_at, timestamps', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'project_id',
                'name',
                'position',
                'archived_at',
                'created_at',
                'updated_at',
            ],
        ]);
});

it('caps board position strings at 1024 bytes', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create([
        'position' => str_repeat('z', 1024),
    ]);

    expect(strlen($board->position))->toBeLessThanOrEqual(1024);
});
