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
    $this->other = User::factory()->create();
});

it('returns 401 on every label endpoint without a bearer token', function (string $method, string $path): void {
    $response = match ($method) {
        'GET' => $this->getJson($path),
        'POST' => $this->postJson($path, []),
        'PUT' => $this->putJson($path, []),
        'DELETE' => $this->deleteJson($path),
    };

    $response->assertUnauthorized();
})->with([
    'index' => ['GET', '/api/v1/kanban-labels'],
    'store' => ['POST', '/api/v1/kanban-labels'],
    'show' => ['GET', '/api/v1/kanban-labels/1'],
    'update' => ['PUT', '/api/v1/kanban-labels/1'],
    'destroy' => ['DELETE', '/api/v1/kanban-labels/1'],
]);

it('lists only the authenticated user labels', function (): void {
    KanbanLabel::factory()->forUser($this->owner)->count(2)->create();
    KanbanLabel::factory()->forUser($this->other)->count(3)->create();

    $response = $this->actingAs($this->owner, 'sanctum')
        ->getJson('/api/v1/kanban-labels')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('creates a label with 201', function (): void {
    $response = $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/kanban-labels', [
            'name' => 'bug',
            'color' => '#ef4444',
        ])
        ->assertCreated();

    expect($response->json('data.name'))->toBe('bug')
        ->and($response->json('data.color'))->toBe('#ef4444');

    expect(KanbanLabel::query()->where('user_id', $this->owner->id)->count())->toBe(1);
});

it('rejects create with invalid color format', function (string $color): void {
    $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/kanban-labels', [
            'name' => 'bad',
            'color' => $color,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['color']);
})->with([
    'no_hash' => ['ef4444'],
    'short_hex' => ['#fff'],
    'rgb' => ['rgb(255,0,0)'],
    'named' => ['red'],
    'empty' => [''],
]);

it('rejects create with missing or oversize name', function (): void {
    $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/kanban-labels', ['color' => '#ef4444'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/kanban-labels', [
            'name' => str_repeat('a', 65),
            'color' => '#ef4444',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('rejects create with duplicate name for the same user', function (): void {
    KanbanLabel::factory()->forUser($this->owner)->create(['name' => 'bug']);

    $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/kanban-labels', [
            'name' => 'bug',
            'color' => '#10b981',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('allows two users to each have a label with the same name', function (): void {
    KanbanLabel::factory()->forUser($this->owner)->create(['name' => 'bug']);
    $this->actingAs($this->other, 'sanctum')
        ->postJson('/api/v1/kanban-labels', [
            'name' => 'bug',
            'color' => '#10b981',
        ])
        ->assertCreated();
});

it('shows a label owned by the authenticated user', function (): void {
    $label = KanbanLabel::factory()->forUser($this->owner)->create();

    $this->actingAs($this->owner, 'sanctum')
        ->getJson("/api/v1/kanban-labels/{$label->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $label->id);
});

it('returns 404 when fetching another user label', function (): void {
    $label = KanbanLabel::factory()->forUser($this->other)->create();

    $this->actingAs($this->owner, 'sanctum')
        ->getJson("/api/v1/kanban-labels/{$label->id}")
        ->assertNotFound();
});

it('updates a label name and color', function (): void {
    $label = KanbanLabel::factory()->forUser($this->owner)->create([
        'name' => 'old',
        'color' => '#ef4444',
    ]);

    $this->actingAs($this->owner, 'sanctum')
        ->putJson("/api/v1/kanban-labels/{$label->id}", [
            'name' => 'new',
            'color' => '#10b981',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'new')
        ->assertJsonPath('data.color', '#10b981');
});

it('updates only the color when only color is sent', function (): void {
    $label = KanbanLabel::factory()->forUser($this->owner)->create([
        'name' => 'bug',
        'color' => '#ef4444',
    ]);

    $this->actingAs($this->owner, 'sanctum')
        ->putJson("/api/v1/kanban-labels/{$label->id}", [
            'color' => '#10b981',
        ])
        ->assertOk();

    $fresh = $label->fresh();
    expect($fresh->name)->toBe('bug')
        ->and($fresh->color)->toBe('#10b981');
});

it('returns 404 when updating another user label', function (): void {
    $label = KanbanLabel::factory()->forUser($this->other)->create();

    $this->actingAs($this->owner, 'sanctum')
        ->putJson("/api/v1/kanban-labels/{$label->id}", [
            'name' => 'taken',
            'color' => '#10b981',
        ])
        ->assertNotFound();
});

it('hard-deletes a label with 204 and removes it from any cards it was on', function (): void {
    $label = KanbanLabel::factory()->forUser($this->owner)->create();
    $project = Project::factory()->forOwner($this->owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();
    $card->labels()->attach($label->id);

    $this->actingAs($this->owner, 'sanctum')
        ->deleteJson("/api/v1/kanban-labels/{$label->id}")
        ->assertNoContent();

    expect(KanbanLabel::query()->find($label->id))->toBeNull();
    // FK cascade removes the pivot row; the card itself is untouched.
    expect($card->fresh()->labels)->toHaveCount(0);
});

it('returns 404 when deleting another user label', function (): void {
    $label = KanbanLabel::factory()->forUser($this->other)->create();

    $this->actingAs($this->owner, 'sanctum')
        ->deleteJson("/api/v1/kanban-labels/{$label->id}")
        ->assertNotFound();
});
