<?php

declare(strict_types=1);

use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use App\Models\Project;
use App\Models\User;
use Tests\TestCase;

it('returns 401 on the clone endpoint without a bearer token', function (): void {
    /** @var TestCase $this */
    $this->postJson('/api/v1/projects/1/kanban/boards/1/clone', [])
        ->assertUnauthorized();
});

it('clones a board with columns and no cards', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $source = KanbanBoard::factory()->forProject($project)->create(['name' => 'Source']);

    $c1 = KanbanColumn::factory()->forBoard($source)->create(['name' => 'Todo']);
    $c2 = KanbanColumn::factory()->forBoard($source)->create(['name' => 'Doing']);
    $c3 = KanbanColumn::factory()->forBoard($source)->create(['name' => 'Done']);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$source->id}/clone")
        ->assertCreated();

    expect($response->json('data.id'))->toBeInt()->not->toBe($source->id);
    expect($response->json('data.name'))->toBe('Source (Copy)');

    $clone = KanbanBoard::query()->findOrFail($response->json('data.id'));
    expect($clone->project_id)->toBe($project->id)
        ->and($clone->position)->toBeString();

    // Verify columns cloned with the same name; cards skipped (cards table is
    // a Batch 4 concern).
    $columns = KanbanColumn::query()->where('board_id', $clone->id)->orderBy('position')->get();
    expect($columns)->toHaveCount(3);
    expect($columns->pluck('name')->all())->toBe(['Todo', 'Doing', 'Done']);

    // Source must be intact.
    $sourceAfter = KanbanBoard::query()->findOrFail($source->id);
    expect($sourceAfter->id)->toBe($source->id)
        ->and($sourceAfter->deleted_at)->toBeNull();
});

it('defaults new name to "{original} (Copy)" when no body name is given', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $source = KanbanBoard::factory()->forProject($project)->create(['name' => 'Alpha']);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$source->id}/clone")
        ->assertCreated();

    expect($response->json('data.name'))->toBe('Alpha (Copy)');
});

it('appends "(Copy 2)" on a (Copy) name collision, "(Copy N)" thereafter', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $source = KanbanBoard::factory()->forProject($project)->create(['name' => 'Beta']);

    // First clone: defaults to "Beta (Copy)".
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$source->id}/clone")
        ->assertCreated()
        ->assertJsonPath('data.name', 'Beta (Copy)');

    // Second clone of the same source: must collide and produce "(Copy 2)".
    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$source->id}/clone")
        ->assertCreated();

    expect($response->json('data.name'))->toBe('Beta (Copy 2)');

    // Third clone: "(Copy 3)".
    $third = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$source->id}/clone")
        ->assertCreated();

    expect($third->json('data.name'))->toBe('Beta (Copy 3)');
});

it('uses the caller-provided name when present and within 100 chars', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $source = KanbanBoard::factory()->forProject($project)->create(['name' => 'Source']);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$source->id}/clone", [
            'name' => 'Custom Name',
        ])
        ->assertCreated();

    expect($response->json('data.name'))->toBe('Custom Name');
});

it('returns 404 when cloning a soft-deleted board', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $source = KanbanBoard::factory()->forProject($project)->create(['name' => 'Trash Me']);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$source->id}")
        ->assertNoContent();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$source->id}/clone")
        ->assertNotFound();
});

it('returns 404 when a non-owner tries to clone a board', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $source = KanbanBoard::factory()->forProject($project)->create(['name' => 'Mine']);

    $this->actingAs($stranger, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$source->id}/clone")
        ->assertNotFound();
});

it('rejects 422 when caller-provided name is longer than 100 chars', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $source = KanbanBoard::factory()->forProject($project)->create(['name' => 'Long']);

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$source->id}/clone", [
            'name' => str_repeat('a', 101),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});
