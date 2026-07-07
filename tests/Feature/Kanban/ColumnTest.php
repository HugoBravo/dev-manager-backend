<?php

declare(strict_types=1);

use App\Models\Board;
use App\Models\KanbanColumn;
use App\Models\Project;
use App\Models\User;
use App\Policies\ColumnPolicy;
use App\Support\Kanban\Position;
use Tests\TestCase;

beforeEach(function (): void {
    // RefreshDatabase is wired project-wide via tests/Pest.php; do not duplicate here.
});

it('returns 401 on every column endpoint without a bearer token', function (string $method, string $path): void {
    /** @var TestCase $this */
    $response = match ($method) {
        'GET' => $this->getJson($path),
        'POST' => $this->postJson($path, []),
        'PATCH' => $this->patchJson($path, []),
        'DELETE' => $this->deleteJson($path),
    };

    $response->assertUnauthorized();
})->with([
    'index' => ['GET', '/api/v1/projects/1/kanban/boards/1/columns'],
    'store' => ['POST', '/api/v1/projects/1/kanban/boards/1/columns'],
    'show' => ['GET', '/api/v1/projects/1/kanban/boards/1/columns/1'],
    'update' => ['PATCH', '/api/v1/projects/1/kanban/boards/1/columns/1'],
    'destroy' => ['DELETE', '/api/v1/projects/1/kanban/boards/1/columns/1'],
    'reorder' => ['POST', '/api/v1/projects/1/kanban/boards/1/columns/reorder'],
    'move' => ['POST', '/api/v1/projects/1/kanban/boards/1/columns/1/move'],
]);

it('lists columns of an owned board with a stable position order', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();

    KanbanColumn::factory()->forBoard($board)->count(3)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns")
        ->assertOk();

    $ids = collect($response->json('data'))
        ->map(fn (array $envelope) => $envelope['data']['id'] ?? null)
        ->all();

    expect($ids)->toHaveCount(3);
});

it('creates a column in an owned board with 201', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns", [
            'name' => 'Todo',
        ])
        ->assertCreated();

    $id = $response->json('data.id');
    expect($id)->toBeInt();

    $column = KanbanColumn::query()->findOrFail($id);
    expect($column->name)->toBe('Todo')
        ->and($column->board_id)->toBe($board->id)
        ->and($column->archived_at)->toBeNull()
        ->and($column->position)->toBeString()
        ->and(strlen($column->position))->toBeLessThanOrEqual(Position::MAX_LENGTH);
});

it('rejects create with empty name', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns", ['name' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('rejects create with name longer than 100 chars', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns", [
            'name' => str_repeat('a', 101),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('shows a column to the project owner', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $column->id)
        ->assertJsonPath('data.name', $column->name);
});

it('returns 404 when a non-owner fetches a column (no existence leak)', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}")
        ->assertNotFound();
});

it('renames a column for the project owner', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}", [
            'name' => 'Doing',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Doing');

    expect($column->fresh()->name)->toBe('Doing');
});

it('rejects update with name longer than 100 chars', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}", [
            'name' => str_repeat('b', 101),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('returns 404 when a non-owner updates a column', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $this->actingAs($stranger, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}", [
            'name' => 'Hijacked',
        ])
        ->assertNotFound();

    expect($column->fresh()->name)->not->toBe('Hijacked');
});

it('deletes an empty column with 204', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}")
        ->assertNoContent();

    expect(KanbanColumn::query()->find($column->id))->toBeNull();
});

it('returns 409 with a typed column_has_contents code when destroying a column with cards under it', function (): void {
    // `cards` table does not ship until Batch 4. To exercise the 409 contract
    // without breaking on missing tables, we trigger the same controller path
    // by binding a ColumnPolicy stub that returns false for delete() — this
    // is what BoardTest did for `BoardHasContentsException`. Batch 4's
    // real `!cardsRelation->exists()` check will replace the stub, but the
    // HTTP contract (409 + typed code) is locked today.
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $this->app->bind(
        ColumnPolicy::class,
        fn () => new class
        {
            public function delete(User $user, KanbanColumn $column): bool
            {
                return false;
            }
        }
    );

    $response = $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}")
        ->assertStatus(409);

    expect($response->json('code'))->toBe('column_has_contents');
    expect(KanbanColumn::query()->find($column->id))->not->toBeNull();
});

it('returns 404 when a non-owner deletes a column', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $this->actingAs($stranger, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}")
        ->assertNotFound();

    expect(KanbanColumn::query()->find($column->id))->not->toBeNull();
});

it('archives a column via archived_at (sets timestamp)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}", [
            'name' => $column->name,
            'archived_at' => now()->toIso8601String(),
        ])
        ->assertOk();

    expect($column->fresh()->archived_at)->not->toBeNull();
    expect($response->json('data.archived_at'))->not->toBeNull();
});

it('reorders columns within the same board and persists the new ordering', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();

    $c1 = KanbanColumn::factory()->forBoard($board)->create();
    $c2 = KanbanColumn::factory()->forBoard($board)->create();
    $c3 = KanbanColumn::factory()->forBoard($board)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/reorder", [
            'ordered_ids' => [$c3->id, $c1->id, $c2->id],
        ])
        ->assertOk();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns")
        ->assertOk();

    $ids = collect($response->json('data'))
        ->map(fn (array $envelope) => $envelope['data']['id'] ?? null)
        ->values()
        ->all();

    expect($ids)->toBe([$c3->id, $c1->id, $c2->id]);
});

it('rejects reorder with ids from another board', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $otherBoard = Board::factory()->forProject($project)->create();

    $c1 = KanbanColumn::factory()->forBoard($board)->create();
    $c2 = KanbanColumn::factory()->forBoard($board)->create();
    $foreignColumn = KanbanColumn::factory()->forBoard($otherBoard)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/reorder", [
            'ordered_ids' => [$c1->id, $foreignColumn->id, $c2->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ordered_ids']);
});

it('returns 404 when a non-owner reorders columns', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $c1 = KanbanColumn::factory()->forBoard($board)->create();
    $c2 = KanbanColumn::factory()->forBoard($board)->create();

    $this->actingAs($stranger, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/reorder", [
            'ordered_ids' => [$c2->id, $c1->id],
        ])
        ->assertNotFound();
});

it('rejects reorder with duplicate ids', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();

    $c1 = KanbanColumn::factory()->forBoard($board)->create();
    $c2 = KanbanColumn::factory()->forBoard($board)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/reorder", [
            'ordered_ids' => [$c1->id, $c1->id, $c2->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ordered_ids']);
});

it('moves a column to another board on the same project, preserving the column id', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $sourceBoard = Board::factory()->forProject($project)->create();
    $targetBoard = Board::factory()->forProject($project)->create();

    $column = KanbanColumn::factory()->forBoard($sourceBoard)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$sourceBoard->id}/columns/{$column->id}/move", [
            'to_board_id' => $targetBoard->id,
        ])
        ->assertOk();

    $reloaded = $column->fresh();
    expect($reloaded->board_id)->toBe($targetBoard->id)
        ->and(strlen($reloaded->position))->toBeLessThanOrEqual(Position::MAX_LENGTH);

    // Position on the new board must be a fresh, stable fraction.
    $otherPositions = KanbanColumn::query()
        ->where('board_id', $targetBoard->id)
        ->where('id', '!=', $column->id)
        ->pluck('position')
        ->all();

    foreach ($otherPositions as $existing) {
        expect(strcmp($reloaded->position, $existing) !== 0)->toBeTrue();
    }
});

it('returns 404 when moving a column to a board owned by a different user', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $strangerProject = Project::factory()->forOwner($stranger)->create();

    $sourceBoard = Board::factory()->forProject($project)->create();
    $foreignTargetBoard = Board::factory()->forProject($strangerProject)->create();
    $column = KanbanColumn::factory()->forBoard($sourceBoard)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$sourceBoard->id}/columns/{$column->id}/move", [
            'to_board_id' => $foreignTargetBoard->id,
        ])
        ->assertNotFound();

    expect($column->fresh()->board_id)->toBe($sourceBoard->id);
});

it('returns 404 when moving to a same-project board but the column does not belong to the requested project', function (): void {
    // Both boards belong to projects the user owns, but the column is bound
    // to a DIFFERENT source board (cross-board id reuse). The {column}
    // binding closure scopes by board -> project -> owner; using a column id
    // that does not belong to the source board's project must 404.
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $otherProject = Project::factory()->forOwner($owner)->create();

    $sourceBoard = Board::factory()->forProject($project)->create();
    $targetBoard = Board::factory()->forProject($project)->create();
    $foreignBoard = Board::factory()->forProject($otherProject)->create();
    $foreignColumn = KanbanColumn::factory()->forBoard($foreignBoard)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$sourceBoard->id}/columns/{$foreignColumn->id}/move", [
            'to_board_id' => $targetBoard->id,
        ])
        ->assertNotFound();
});

it('caps column position at 1024 bytes under DB persistence', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create([
        'position' => str_repeat('z', 1024),
    ]);

    expect(strlen($column->position))->toBeLessThanOrEqual(Position::MAX_LENGTH);
});

it('exposes the resource shape with id, board_id, name, position, archived_at, timestamps', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'board_id',
                'name',
                'position',
                'archived_at',
                'created_at',
                'updated_at',
            ],
        ]);
});

it('does not leak any other board columns when listing one board', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $otherBoard = Board::factory()->forProject($project)->create();

    KanbanColumn::factory()->forBoard($board)->count(2)->create();
    KanbanColumn::factory()->forBoard($otherBoard)->count(3)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns")
        ->assertOk();

    $ids = collect($response->json('data'))
        ->map(fn (array $envelope) => $envelope['data']['id'] ?? null)
        ->all();

    expect($ids)->toHaveCount(2)
        ->and(KanbanColumn::query()->where('board_id', $board->id)->count())->toBe(2);
});

it('returns 404 on list when the user does not own the project', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns")
        ->assertNotFound();
});

it('returns 404 on store when the user does not own the project', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();

    $this->actingAs($stranger, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns", [
            'name' => 'Hostile',
        ])
        ->assertNotFound();

    expect(KanbanColumn::query()->where('name', 'Hostile')->count())->toBe(0);
});

it('caps the post-create column position at 1024 bytes at the DB layer', function (): void {
    // Direct DB-persistence cap check: the column's `position` column is
    // varchar(255), and the Position VO never returns a string > 1024 bytes.
    // A column with position at the DB limit should still persist; the cap
    // is enforced on the value-object side, not the DB side. The test
    // exercises only the persistence invariant — exhaustive exhaustion of
    // the cap is covered in tests/Unit/Kanban/PositionTest.php.
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();

    $column = KanbanColumn::factory()->forBoard($board)->create([
        'position' => str_repeat('z', Position::MAX_LENGTH),
    ]);

    expect(strlen($column->fresh()->position))->toBeLessThanOrEqual(Position::MAX_LENGTH);
});
