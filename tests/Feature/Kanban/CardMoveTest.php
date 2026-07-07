<?php

declare(strict_types=1);

use App\Models\Board;
use App\Models\Card;
use App\Models\KanbanColumn;
use App\Models\Project;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| CardMoveTest (Batch 4 — sdd/kanban/tasks Phase 4)
|--------------------------------------------------------------------------
|
| Split out of CardTest.php when the brief's 500-line threshold was exceeded.
| Covers the cross-column move verb (`POST .../cards/{card}/move`) and the
| within-column reorder verb (`POST .../cards/reorder`).
|
| Mirrors Batch 3 R3: target in another project of the same owner → 404 not 422.
| Existence-leak avoidance on move.
|
*/

it('moves a card cross-column and preserves stable ascending position order in the destination', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $sourceColumn = KanbanColumn::factory()->forBoard($board)->create();
    $targetColumn = KanbanColumn::factory()->forBoard($board)->create();

    // Seed source with 2 cards (a0, a1) and target with 2 cards (a0, a1)
    $sourceA = Card::factory()->forColumn($sourceColumn)->create();
    Card::factory()->forColumn($sourceColumn)->create();
    Card::factory()->forColumn($targetColumn)->create();
    Card::factory()->forColumn($targetColumn)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$sourceColumn->id}/cards/{$sourceA->id}/move", [
            'to_column_id' => $targetColumn->id,
        ])
        ->assertOk();

    // The moved card must now be in targetColumn.
    expect($sourceA->fresh()->column_id)->toBe($targetColumn->id);

    // Fetch destination column and assert ascending position invariant on visible (non-archived) cards.
    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$targetColumn->id}/cards")
        ->assertOk();

    $cards = collect($response->json('data'))
        ->map(fn (array $envelope) => $envelope['data'] ?? $envelope)
        ->all();

    $positions = array_column($cards, 'position');
    // No duplicates.
    expect(array_unique($positions))->toHaveCount(count($positions));

    // Monotonic ascending when sorted.
    $sorted = $positions;
    sort($sorted, SORT_STRING);
    expect($positions)->toBe($sorted);
});

it('returns 404 when moving a card to a column in a different project of the SAME owner', function (): void {
    // Convention mirror (Batch 3 R3): target in another project of the same owner → 404 not 422.
    // Existence-leak avoidance: the target_id surfaces as "not found" so an attacker can't
    // probe another project's columns through the move endpoint.
    $owner = User::factory()->create();
    $projectA = Project::factory()->forOwner($owner)->create();
    $projectB = Project::factory()->forOwner($owner)->create();

    $boardA = Board::factory()->forProject($projectA)->create();
    $boardB = Board::factory()->forProject($projectB)->create();

    $sourceColumn = KanbanColumn::factory()->forBoard($boardA)->create();
    $foreignColumn = KanbanColumn::factory()->forBoard($boardB)->create();

    $card = Card::factory()->forColumn($sourceColumn)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$projectA->id}/kanban/boards/{$boardA->id}/columns/{$sourceColumn->id}/cards/{$card->id}/move", [
            'to_column_id' => $foreignColumn->id,
        ])
        ->assertNotFound();

    expect($card->fresh()->column_id)->toBe($sourceColumn->id);
});

it('returns 404 when a stranger moves a card', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $sourceColumn = KanbanColumn::factory()->forBoard($board)->create();
    $targetColumn = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($sourceColumn)->create();

    $this->actingAs($stranger, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$sourceColumn->id}/cards/{$card->id}/move", [
            'to_column_id' => $targetColumn->id,
        ])
        ->assertNotFound();

    expect($card->fresh()->column_id)->toBe($sourceColumn->id);
});

it('reorders cards within a column and persists the new order', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $a = Card::factory()->forColumn($column)->create();
    $b = Card::factory()->forColumn($column)->create();
    $c = Card::factory()->forColumn($column)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/reorder", [
            'ordered_ids' => [$c->id, $a->id, $b->id],
        ])
        ->assertOk();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards")
        ->assertOk();

    $ids = collect($response->json('data'))
        ->map(fn (array $envelope) => $envelope['data']['id'] ?? $envelope['id'] ?? null)
        ->values()
        ->all();

    expect($ids)->toBe([$c->id, $a->id, $b->id]);
});

it('rejects reorder with duplicate ids', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $a = Card::factory()->forColumn($column)->create();
    Card::factory()->forColumn($column)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/reorder", [
            'ordered_ids' => [$a->id, $a->id, $a->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ordered_ids']);
});

it('returns 404 when a stranger reorders cards', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $a = Card::factory()->forColumn($column)->create();
    $b = Card::factory()->forColumn($column)->create();

    $this->actingAs($stranger, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/reorder", [
            'ordered_ids' => [$b->id, $a->id],
        ])
        ->assertNotFound();
});
