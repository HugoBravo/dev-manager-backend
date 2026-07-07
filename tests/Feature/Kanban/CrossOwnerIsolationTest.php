<?php

declare(strict_types=1);

use App\Models\KanbanAttachment;
use App\Models\KanbanBoard;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Models\KanbanComment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| CrossOwnerIsolationTest (Batch 7 — closure-binding 404 verification)
|--------------------------------------------------------------------------
|
| Pact-style cross-cut test that exercises the 404-not-403 contract for
| EVERY nested resource type at the chokepoint-binding layer. Each scenario
| uses a known attacker to assert that:
|
|   1. The owner's own resource is reachable (sanity baseline — 200).
|   2. The attacker's request against the SAME resource id returns 404
|      (NOT 403) — this prevents existence leaks.
|   3. The Resource Type Parametrized dataset covers board, column, card,
|      comment, and attachment in a single dataset sweep so a future
|      regression in one binding closure is caught immediately.
|
| These tests target the `Route::bind(...)` closures registered in
| AppServiceProvider::boot(). They intentionally do NOT route through
| show/update/destroy's "ensureBelongsToX" helpers because the contract
| is that the binding ALONE produces 404 cross-owner.
|
*/

beforeEach(function (): void {
    Storage::fake('local');
});

/**
 * Owner can reach their own project + board — sanity baseline for the
 * chain. If this fails the closures may be incorrectly scoping OUT
 * legitimate owner requests, so the test catches both regressions.
 */
it('lets the owner reach their own board index', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    KanbanBoard::factory()->forProject($project)->count(2)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards")
        ->assertOk()
        ->assertJsonStructure(['data']);
});

/**
 * Core cross-owner assertion: an attacker user requesting a project that
 * is NOT theirs MUST receive 404, never 403 — the existence-leak contract
 * from design §7. The test is explicit about the status to catch any
 * future regression where `Gate::authorize` (which surfaces 403) is added
 * to the binding closure path.
 */
it('returns 404 (not 403) when an attacker fetches another user project board', function (): void {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();

    // Explicit assertion comment — this MUST be 404, NOT 403, by design.
    $this->actingAs($attacker, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}")
        ->assertNotFound();
});

/**
 * R1 RESOLUTION (Batch 7): an archived project filters nested resources
 * by default. Without `?include_archived=1` the board index returns an
 * empty envelope. The request tree honors the flag end-to-end.
 */
it('returns empty board list when the project is archived and the flag is omitted', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create(['archived_at' => now()]);
    KanbanBoard::factory()->forProject($project)->count(3)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns boards when the project is archived and ?include_archived=1 is set', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create(['archived_at' => now()]);
    KanbanBoard::factory()->forProject($project)->count(3)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards?include_archived=1")
        ->assertOk();

    // 3 boards → paginated data array length 3 (page size = 25).
    expect($this->getJson("/api/v1/projects/{$project->id}/kanban/boards?include_archived=1")
        ->json('data'))->toHaveCount(3);
});

/**
 * R1 RESOLUTION: deep nested show endpoints honour `include_archived=1`
 * too — the flag must propagate down the chain. A card show on an
 * archived-project board returns 404 by default, 200 with the flag.
 */
it('returns 404 on a card show when the project is archived and the flag is omitted', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create(['archived_at' => now()]);
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}")
        ->assertNotFound();
});

it('returns 200 on a card show when the project is archived and ?include_archived=1 is set', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create(['archived_at' => now()]);
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}?include_archived=1")
        ->assertOk()
        ->assertJsonPath('data.id', $card->id);
});

/**
 * Parametrized cross-owner sweep: ONE test, FIVE resource types. Each
 * dataset row builds an attacker + victim owner + a real resource for
 * the victim, then asserts the attacker's GET resolves to 404. A
 * regression in any single Route::bind closure is caught here even if
 * the dedicated tests above pass.
 */
it('returns 404 to an attacker on every nested resource type', function (string $resourceType): void {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();

    // Build the full chain — every nested resource needs its parents.
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();
    $comment = KanbanComment::factory()->forCard($card)->byAuthor($owner)->create();
    $attachment = KanbanAttachment::factory()->forCard($card)->byUploader($owner)->create();

    $method = 'GET';
    $path = match ($resourceType) {
        'project' => "/api/v1/projects/{$project->id}",
        'board' => "/api/v1/projects/{$project->id}/kanban/boards/{$board->id}",
        'column' => "/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}",
        'card' => "/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}",
        'comment' => "/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments/{$comment->id}",
        // Attachments only have index/store/destroy (no show) — exercise DELETE
        // to verify the binding closure scopes cross-owner correctly.
        'attachment' => (function () use ($project, $board, $column, $card, $attachment): array {
            return ['DELETE', "/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/attachments/{$attachment->id}"];
        })(),
        default => throw new InvalidArgumentException("Unknown resource type: {$resourceType}"),
    };

    if (is_array($path)) {
        [$method, $path] = $path;
    }

    // The contract: 404 cross-owner, never 403 (existence-leak guard).
    $response = match ($method) {
        'GET' => $this->actingAs($attacker, 'sanctum')->getJson($path),
        'DELETE' => $this->actingAs($attacker, 'sanctum')->deleteJson($path),
        default => throw new InvalidArgumentException("Unsupported method: {$method}"),
    };

    $response->assertNotFound();

    // Assert the resource still exists after the attacker's failed request —
    // proves the binding returned 404 (not 403 which would not mutate state
    // either, but explicitly asserts idempotence and non-mutation contract).
    if ($resourceType === 'attachment') {
        expect(KanbanAttachment::query()->whereKey($attachment->id)->exists())->toBeTrue();
    }
})->with([
    'project' => ['project'],
    'board' => ['board'],
    'column' => ['column'],
    'card' => ['card'],
    'comment' => ['comment'],
    'attachment' => ['attachment'],
]);
