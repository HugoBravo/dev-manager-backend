<?php

declare(strict_types=1);

use App\Models\KanbanBoard;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Models\KanbanLabel;
use App\Models\Project;
use App\Models\User;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->stranger = User::factory()->create();

    $this->project = Project::factory()->forOwner($this->owner)->create();
    $this->board = KanbanBoard::factory()->forProject($this->project)->create();
    $this->column = KanbanColumn::factory()->forBoard($this->board)->create();
    $this->card = KanbanCard::factory()->forColumn($this->column)->create();
});

function cardLabelsSyncPath(KanbanCard $card, KanbanBoard $board, KanbanColumn $column, Project $project): string
{
    return kanbanPrefix($project)."/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/labels";
}

it('returns 401 on the card labels sync endpoint without a bearer token', function (): void {
    $this->putJson(cardLabelsSyncPath($this->card, $this->board, $this->column, $this->project), [
        'label_ids' => [],
    ])->assertUnauthorized();
});

it('syncs labels on a card (replaces the set)', function (): void {
    $keep = KanbanLabel::factory()->forUser($this->owner)->create(['name' => 'keep']);
    $add = KanbanLabel::factory()->forUser($this->owner)->create(['name' => 'add']);
    $remove = KanbanLabel::factory()->forUser($this->owner)->create(['name' => 'remove']);
    $this->card->labels()->attach($remove->id);

    $response = $this->actingAs($this->owner, 'sanctum')
        ->putJson(cardLabelsSyncPath($this->card, $this->board, $this->column, $this->project), [
            'label_ids' => [$keep->id, $add->id],
        ])
        ->assertOk();

    $payloadLabels = collect($response->json('data.labels'))->pluck('id')->all();
    expect($payloadLabels)->toEqualCanonicalizing([$keep->id, $add->id]);

    $attached = $this->card->fresh()->labels->pluck('id')->all();
    expect($attached)->toEqualCanonicalizing([$keep->id, $add->id]);
});

it('clears all labels when label_ids is an empty array', function (): void {
    $label = KanbanLabel::factory()->forUser($this->owner)->create();
    $this->card->labels()->attach($label->id);

    $this->actingAs($this->owner, 'sanctum')
        ->putJson(cardLabelsSyncPath($this->card, $this->board, $this->column, $this->project), [
            'label_ids' => [],
        ])
        ->assertOk()
        ->assertJsonPath('data.labels', []);

    expect($this->card->fresh()->labels)->toHaveCount(0);
});

it('rejects sync with a label belonging to another user (422)', function (): void {
    $mine = KanbanLabel::factory()->forUser($this->owner)->create();
    $strangerLabel = KanbanLabel::factory()->forUser($this->stranger)->create();

    $this->actingAs($this->owner, 'sanctum')
        ->putJson(cardLabelsSyncPath($this->card, $this->board, $this->column, $this->project), [
            'label_ids' => [$mine->id, $strangerLabel->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['label_ids.1']);

    // The card is NOT mutated when validation fails.
    expect($this->card->fresh()->labels)->toHaveCount(0);
});

it('rejects sync with a non-existent label id (422)', function (): void {
    $this->actingAs($this->owner, 'sanctum')
        ->putJson(cardLabelsSyncPath($this->card, $this->board, $this->column, $this->project), [
            'label_ids' => [999_999],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['label_ids.0']);
});

it('rejects sync with duplicate label ids (422)', function (): void {
    $label = KanbanLabel::factory()->forUser($this->owner)->create();

    $this->actingAs($this->owner, 'sanctum')
        ->putJson(cardLabelsSyncPath($this->card, $this->board, $this->column, $this->project), [
            'label_ids' => [$label->id, $label->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['label_ids.1']);
});

it('requires label_ids to be present (even if empty)', function (): void {
    $this->actingAs($this->owner, 'sanctum')
        ->putJson(cardLabelsSyncPath($this->card, $this->board, $this->column, $this->project), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['label_ids']);
});

it('returns 404 when a non-owner syncs labels on a card', function (): void {
    $label = KanbanLabel::factory()->forUser($this->stranger)->create();

    $this->actingAs($this->stranger, 'sanctum')
        ->putJson(cardLabelsSyncPath($this->card, $this->board, $this->column, $this->project), [
            'label_ids' => [$label->id],
        ])
        ->assertNotFound();

    expect($this->card->fresh()->labels)->toHaveCount(0);
});

it('returns 404 when syncing labels on a card from a different column on the same board', function (): void {
    $otherColumn = KanbanColumn::factory()->forBoard($this->board)->create();
    $label = KanbanLabel::factory()->forUser($this->owner)->create();

    $this->actingAs($this->owner, 'sanctum')
        ->putJson(
            kanbanPrefix($this->project)."/boards/{$this->board->id}/columns/{$otherColumn->id}/cards/{$this->card->id}/labels",
            ['label_ids' => [$label->id]],
        )
        ->assertNotFound();
});

it('returns 404 when the project is archived and the include_archived flag is omitted', function (): void {
    $this->project->update(['archived_at' => now()]);
    $label = KanbanLabel::factory()->forUser($this->owner)->create();

    $this->actingAs($this->owner, 'sanctum')
        ->putJson(cardLabelsSyncPath($this->card, $this->board, $this->column, $this->project), [
            'label_ids' => [$label->id],
        ])
        ->assertNotFound();
});

it('exposes labels in the card resource when listing cards of a column', function (): void {
    $label = KanbanLabel::factory()->forUser($this->owner)->create(['name' => 'urgent', 'color' => '#ef4444']);
    $this->card->labels()->attach($label->id);

    $response = $this->actingAs($this->owner, 'sanctum')
        ->getJson(kanbanPrefix($this->project)."/boards/{$this->board->id}/columns/{$this->column->id}/cards")
        ->assertOk();

    $first = $response->json('data.0.data');
    expect($first['labels'])->toHaveCount(1)
        ->and($first['labels'][0]['name'])->toBe('urgent')
        ->and($first['labels'][0]['color'])->toBe('#ef4444');
});

it('exposes labels as an empty array when the card has no labels', function (): void {
    $this->actingAs($this->owner, 'sanctum')
        ->getJson(kanbanPrefix($this->project)."/boards/{$this->board->id}/columns/{$this->column->id}/cards/{$this->card->id}")
        ->assertOk()
        ->assertJsonPath('data.labels', []);
});
