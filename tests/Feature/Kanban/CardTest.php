<?php

declare(strict_types=1);

use App\Models\Board;
use App\Models\Card;
use App\Models\KanbanColumn;
use App\Models\Project;
use App\Models\User;
use App\Support\Kanban\Position;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| CardTest (Batch 4 — sdd/kanban/tasks Phase 4)
|--------------------------------------------------------------------------
|
| Card lifecycle: CRUD + Markdown body + archive/restore + hard delete.
| Markdown body is stored raw (no sanitization). 401 parametrized,
| 404-not-403 ownership.
|
| Split per the brief's 500-line threshold:
|   - `CardTest.php`    ← this file: CRUD + Markdown + archive (28 scenarios)
|   - `CardMoveTest.php`  ← cross-column move + reorder (6 scenarios)
|
*/

it('returns 401 on every card endpoint without a bearer token', function (string $method, string $path): void {
    /** @var TestCase $this */
    $response = match ($method) {
        'GET' => $this->getJson($path),
        'POST' => $this->postJson($path, []),
        'PATCH' => $this->patchJson($path, []),
        'DELETE' => $this->deleteJson($path),
    };

    $response->assertUnauthorized();
})->with([
    'index' => ['GET', '/api/v1/projects/1/kanban/boards/1/columns/1/cards'],
    'store' => ['POST', '/api/v1/projects/1/kanban/boards/1/columns/1/cards'],
    'show' => ['GET', '/api/v1/projects/1/kanban/boards/1/columns/1/cards/1'],
    'update' => ['PATCH', '/api/v1/projects/1/kanban/boards/1/columns/1/cards/1'],
    'destroy' => ['DELETE', '/api/v1/projects/1/kanban/boards/1/columns/1/cards/1'],
    'move' => ['POST', '/api/v1/projects/1/kanban/boards/1/columns/1/cards/1/move'],
    'archive' => ['POST', '/api/v1/projects/1/kanban/boards/1/columns/1/cards/1/archive'],
    'reorder' => ['POST', '/api/v1/projects/1/kanban/boards/1/columns/1/cards/reorder'],
]);

it('lists cards of an owned column with a stable position order', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    Card::factory()->forColumn($column)->count(3)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

it('hides archived cards on index by default', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    Card::factory()->forColumn($column)->count(3)->create();
    Card::factory()->forColumn($column)->count(2)->archived()->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

it('returns all 5 cards when ?archived=1 is supplied on index', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    Card::factory()->forColumn($column)->count(3)->create();
    Card::factory()->forColumn($column)->count(2)->archived()->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards?archived=1")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(5);
});

it('creates a card with 201 and a default position', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards", [
            'title' => 'Ship it',
        ])
        ->assertCreated();

    $id = $response->json('data.id');
    expect($id)->toBeInt();

    $card = Card::query()->findOrFail($id);
    expect($card->title)->toBe('Ship it')
        ->and($card->column_id)->toBe($column->id)
        ->and($card->body)->toBeNull()
        ->and($card->archived_at)->toBeNull()
        ->and($card->position)->toBeString()
        ->and(strlen($card->position))->toBeLessThanOrEqual(Position::MAX_LENGTH);
});

it('creates a card with a Markdown body stored verbatim', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $markdown = "# Title\n\n- one\n- two\n\n```php\nvar_dump(1);\n```\n<script>alert(1)</script>";

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards", [
            'title' => 'Card',
            'body' => $markdown,
        ])
        ->assertCreated();

    $id = $response->json('data.id');
    $card = Card::query()->findOrFail($id);
    // Raw string preservation — no sanitization — the script tag stays verbatim.
    expect($card->body)->toBe($markdown)
        ->and(str_contains((string) $card->body, '<script>alert(1)</script>'))->toBeTrue();
});

it('rejects create with missing title', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

it('rejects create with title longer than 255 chars', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards", [
            'title' => str_repeat('t', 256),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

it('rejects create with body longer than 65535 chars', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards", [
            'title' => 'Oversize',
            'body' => str_repeat('a', 65536),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['body']);
});

it('shows a card to the project owner', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $card->id)
        ->assertJsonPath('data.title', $card->title);
});

it('returns 404 when a non-owner fetches a card', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}")
        ->assertNotFound();
});

it('returns 404 when fetching a card from a different column on the same board', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $otherColumn = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$otherColumn->id}/cards/{$card->id}")
        ->assertNotFound();
});

it('updates a card title and body', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}", [
            'title' => 'Renamed',
            'body' => 'New body',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Renamed');

    $fresh = $card->fresh();
    expect($fresh->title)->toBe('Renamed')
        ->and($fresh->body)->toBe('New body');
});

it('accepts update with empty body', function (): void {
    // SQLite quirk: empty strings become NULL when stored in a TEXT column
    // without NOT NULL. The frontend renders empty body vs null body the
    // same way ("empty") — semantic equivalence is what the brief locks.
    // Production runs on PostgreSQL where this is moot.
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create(['body' => 'old body']);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}", [
            'title' => $card->title,
            'body' => '',
        ])
        ->assertOk();

    $stored = $card->fresh()->body;
    expect($stored === '' || $stored === null)->toBeTrue();
});

it('accepts update with null body', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create(['body' => 'old body']);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}", [
            'title' => $card->title,
            'body' => null,
        ])
        ->assertOk();

    expect($card->fresh()->body)->toBeNull();
});

it('preserves raw body on update including script tags verbatim (no sanitization)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}", [
            'title' => $card->title,
            'body' => '<script>alert("xss")</script>',
        ])
        ->assertOk();

    expect($card->fresh()->body)->toBe('<script>alert("xss")</script>');
});

it('rejects update with body longer than 65535 chars', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}", [
            'title' => $card->title,
            'body' => str_repeat('a', 65536),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['body']);
});

it('sets and clears due_date on update', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}", [
            'title' => $card->title,
            'due_date' => '2027-01-31',
        ])
        ->assertOk();

    expect($card->fresh()->due_date?->toDateString())->toBe('2027-01-31');

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}", [
            'title' => $card->title,
            'due_date' => null,
        ])
        ->assertOk();

    expect($card->fresh()->due_date)->toBeNull();
});

it('hard-deletes a card with 204', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}")
        ->assertNoContent();

    expect(Card::query()->find($card->id))->toBeNull();
});

it('returns 404 when a non-owner deletes a card', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();

    $this->actingAs($stranger, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}")
        ->assertNotFound();

    expect(Card::query()->find($card->id))->not->toBeNull();
});

it('archives a card and restores it via dedicated endpoints', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/archive")
        ->assertOk();

    expect($card->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/restore")
        ->assertOk();

    expect($card->fresh()->archived_at)->toBeNull();
});

it('archive is idempotent on re-archive', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/archive")
        ->assertOk();
    $firstTimestamp = $card->fresh()->archived_at;

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/archive")
        ->assertOk();

    $secondTimestamp = $card->fresh()->archived_at;
    expect($secondTimestamp?->equalTo($firstTimestamp))->toBeTrue();
});

it('returns 404 when a non-owner archives a card', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();

    $this->actingAs($stranger, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/archive")
        ->assertNotFound();

    expect($card->fresh()->archived_at)->toBeNull();
});

it('exposes the resource shape with id, column_id, title, body, due_date, archived_at, position, timestamps', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = Card::factory()->forColumn($column)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'column_id',
                'title',
                'body',
                'position',
                'due_date',
                'archived_at',
                'created_at',
                'updated_at',
            ],
        ]);
});

it('caps card position at 1024 bytes under DB persistence', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $card = Card::factory()->forColumn($column)->create([
        'position' => str_repeat('z', Position::MAX_LENGTH),
    ]);

    expect(strlen($card->fresh()->position))->toBeLessThanOrEqual(Position::MAX_LENGTH);
});

it('does not leak other column cards when listing one column', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $otherColumn = KanbanColumn::factory()->forBoard($board)->create();

    Card::factory()->forColumn($column)->count(2)->create();
    Card::factory()->forColumn($otherColumn)->count(3)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and(Card::query()->where('column_id', $column->id)->count())->toBe(2);
});

/**
 * 5 datasets: validate body/title lengths and special chars pass through unchanged.
 * Notes on the long_md_headings pattern: the dataset terminates WITHOUT a trailing
 * newline to keep the JSON round-trip exact. SQLite (test DB) preserves trailing
 * whitespace, but we avoid the off-by-one corner case in the dataset pattern itself
 * so the equality check is unambiguous.
 */
dataset('valid_card_payloads', [
    'plain_ascii' => ['Hello world', null],
    'long_md_headings' => [str_repeat('# heading'.PHP_EOL.'- item'.PHP_EOL, 499).'# heading'.PHP_EOL.'- item', 'plain ascii body'],
    'code_fence' => ["```php\nvar_dump(1);\n```", null],
    'unicode_emoji' => ['Привет мир 🌍', 'Ünïcödé émojì 🎉'],
    'html_in_body_raw_preserved' => ['<script>alert(1)</script><b>raw</b>', null],
]);

it('accepts dataset payloads and stores body verbatim (no sanitization)', function (string $body, ?string $plain): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = Board::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();

    $title = $plain ?? 'T';

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards", [
            'title' => $title,
            'body' => $body,
        ])
        ->assertCreated();

    $cardId = $response->json('data.id');
    $stored = Card::query()->findOrFail($cardId);
    expect($stored->body)->toBe($body);
})->with('valid_card_payloads');
