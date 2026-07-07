<?php

declare(strict_types=1);

use App\Models\KanbanBoard;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Models\KanbanComment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| CommentTest (Batch 5 — sdd/kanban/tasks Phase 5)
|--------------------------------------------------------------------------
|
| Card comments:
|  - thread-per-author (parent_id only when same-author reply on own root)
|  - 15-minute edit window via Carbon::setTestNow()
|  - 404 cross-owner (binding closure)
|  - 403 author-vs-author (documented exception, NOT 404)
|  - cross-card parent_id → 422
|  - body NOT raw like cards.body — canonical text, length cap 1-5000, reject empty
|  - 401 unauthenticated
|
*/

it('returns 401 on every comment endpoint without a bearer token', function (string $method, string $path): void {
    $response = match ($method) {
        'GET' => $this->getJson($path),
        'POST' => $this->postJson($path, []),
        'PATCH' => $this->patchJson($path, []),
        'DELETE' => $this->deleteJson($path),
    };

    $response->assertUnauthorized();
})->with([
    'index' => ['GET', '/api/v1/projects/1/kanban/boards/1/columns/1/cards/1/comments'],
    'store' => ['POST', '/api/v1/projects/1/kanban/boards/1/columns/1/cards/1/comments'],
    'show' => ['GET', '/api/v1/projects/1/kanban/boards/1/columns/1/cards/1/comments/1'],
    'update' => ['PATCH', '/api/v1/projects/1/kanban/boards/1/columns/1/cards/1/comments/1'],
    'destroy' => ['DELETE', '/api/v1/projects/1/kanban/boards/1/columns/1/cards/1/comments/1'],
]);

it('lists comments for a card as a paginated envelope with 25 per page', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();
    KanbanComment::factory()->forCard($card)->byAuthor($owner)->count(3)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('meta.per_page'))->toBe(25);
});

it('filters comments by parent_id', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $root = KanbanComment::factory()->forCard($card)->byAuthor($owner)->create();
    KanbanComment::factory()->forCard($card)->byAuthor($owner)->forParent($root)->count(2)->create();
    KanbanComment::factory()->forCard($card)->byAuthor($owner)->count(2)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments?parent_id={$root->id}")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('creates a top-level comment with 201 and default author', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments", [
            'body' => 'My first comment',
        ])
        ->assertCreated();

    expect($response->json('data.body'))->toBe('My first comment')
        ->and($response->json('data.parent_id'))->toBeNull()
        ->and($response->json('data.author_id'))->toBe($owner->id);

    $this->assertDatabaseHas('kanban_comments', [
        'card_id' => $card->id,
        'author_id' => $owner->id,
        'parent_id' => null,
        'body' => 'My first comment',
    ]);
});

it('rejects create with empty body string', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments", [
            'body' => '',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['body']);
});

it('rejects create with body longer than 5000 chars', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments", [
            'body' => str_repeat('a', 5001),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['body']);
});

it('accepts body with HTML tags stored verbatim (canonical text, no raw-injection of cards.body semantics)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $body = '<b>bold</b> and <script>alert(1)</script>';

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments", [
            'body' => $body,
        ])
        ->assertCreated();

    expect($response->json('data.body'))->toBe($body);
});

it('accepts a same-author reply as a child comment on the parents own root', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();
    $root = KanbanComment::factory()->forCard($card)->byAuthor($owner)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments", [
            'body' => 'Same-author reply',
            'parent_id' => $root->id,
        ])
        ->assertCreated();

    expect($response->json('data.parent_id'))->toBe($root->id);
});

it('rejects a parent_id owned by a different author (cross-author thread banned at validation layer)', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    // Directly insert a root authored by a stranger — the form-request validation
    // runs BEFORE the route-binding authorization, so the validation rule
    // (parent.author_id === auth user.id) fires and returns 422 even though
    // a future membership model could change the access layer. This proves the
    // invariant at the validation layer rather than via the route binding.
    $root = KanbanComment::query()->forceCreate([
        'card_id' => $card->id,
        'author_id' => $stranger->id,
        'parent_id' => null,
        'body' => 'Pre-existing root by stranger',
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments", [
            'body' => 'Reply to stranger',
            'parent_id' => $root->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['parent_id']);
});

it('rejects a parent_id that belongs to a comment on a different card (cross-card parent)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $cardA = KanbanCard::factory()->forColumn($column)->create();
    $cardB = KanbanCard::factory()->forColumn($column)->create();

    $rootOnB = KanbanComment::factory()->forCard($cardB)->byAuthor($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$cardA->id}/comments", [
            'body' => 'cross-card parent',
            'parent_id' => $rootOnB->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['parent_id']);
});

it('renders thread-per-author: same-author reply uses parent_id and appears under that root', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    // Three sequential comments by the SAME author produce a parent-child
    // thread shape (root -> child -> grandchild) once the owner starts
    // replying to their own root.
    $root = KanbanComment::factory()->forCard($card)->byAuthor($owner)->create();

    $child = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments", [
            'body' => 'child 1',
            'parent_id' => $root->id,
        ])
        ->assertCreated();

    expect($child->json('data.parent_id'))->toBe($root->id);

    $list = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments")
        ->assertOk();

    // 2 comments total — the original root (parent_id null) and the child
    // (parent_id = root.id). Thread-per-author: same-author replies nest
    // under the root, NOT create a sibling.
    $parents = collect($list->json('data'))->pluck('data.parent_id')->all();
    expect($parents)->toContain($root->id)
        ->and($parents)->toContain(null);
});

it('updates a comment by its author within the edit window', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();
    $comment = KanbanComment::factory()->forCard($card)->byAuthor($owner)->create();

    Carbon::setTestNow(now());

    $response = $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments/{$comment->id}", [
            'body' => 'Edited within window',
        ])
        ->assertOk();

    expect($response->json('data.body'))->toBe('Edited within window');

    Carbon::setTestNow();
});

it('returns 404 (binding fires first) when a non-owner tries to edit a comment in v1 (no membership yet)', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();
    $comment = KanbanComment::factory()->forCard($card)->byAuthor($owner)->create();

    // In v1 a single project has one owner. A second author's HTTP request
    // hits the binding closure FIRST and returns 404 (route ownership)
    // — never reaches the 403 policy layer. The documented 403 EXCEPTION
    // is exercised at the policy layer in CommentPolicyTest; it will fire
    // for in-project members once a future change adds the `members` pivot.
    $this->actingAs($stranger, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments/{$comment->id}", [
            'body' => 'stranger edit attempt',
        ])
        ->assertNotFound();
});

it('returns 422 when a comment edit lands beyond the configured edit window', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $commentCreatedAt = now()->subMinutes((int) config('kanban.comment_edit_window_minutes') + 1);
    Carbon::setTestNow($commentCreatedAt);
    $comment = KanbanComment::factory()->forCard($card)->byAuthor($owner)->create();
    Carbon::setTestNow($commentCreatedAt->copy()->addMinutes((int) config('kanban.comment_edit_window_minutes') + 1));

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments/{$comment->id}", [
            'body' => 'too late',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['body']);

    Carbon::setTestNow();
});

it('honors the configured edit window value (env override of 1 minute flips the boundary)', function (): void {
    config(['kanban.comment_edit_window_minutes' => 1]);

    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    Carbon::setTestNow(now()->subMinutes(2));
    $comment = KanbanComment::factory()->forCard($card)->byAuthor($owner)->create();
    Carbon::setTestNow();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments/{$comment->id}", [
            'body' => 'edited',
        ])
        ->assertStatus(422);

    Carbon::setTestNow();
});

it('destroys a comment by its author inside the window with 204', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();
    $comment = KanbanComment::factory()->forCard($card)->byAuthor($owner)->create();

    Carbon::setTestNow(now());

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments/{$comment->id}")
        ->assertNoContent();

    expect($comment->fresh())->toBeNull();

    Carbon::setTestNow();
});

it('returns 422 when a comment delete lands beyond the window', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();

    $created = now()->subMinutes((int) config('kanban.comment_edit_window_minutes') + 5);
    Carbon::setTestNow($created);
    $comment = KanbanComment::factory()->forCard($card)->byAuthor($owner)->create();
    Carbon::setTestNow($created->copy()->addMinutes((int) config('kanban.comment_edit_window_minutes') + 5));

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments/{$comment->id}")
        ->assertStatus(422);

    expect($comment->fresh())->not->toBeNull();

    Carbon::setTestNow();
});

it('returns 404 when a non-owner tries to delete a comment (binding fires first)', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();
    $comment = KanbanComment::factory()->forCard($card)->byAuthor($owner)->create();

    Carbon::setTestNow(now());

    $this->actingAs($stranger, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments/{$comment->id}")
        ->assertNotFound();

    expect($comment->fresh())->not->toBeNull();

    Carbon::setTestNow();
});

it('returns 404 when a stranger (cross-owner) tries to show a comment', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();
    $comment = KanbanComment::factory()->forCard($card)->byAuthor($owner)->create();

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments/{$comment->id}")
        ->assertNotFound();
});

it('returns 404 when the binding closure fires for a comment on another users project', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();
    $comment = KanbanComment::factory()->forCard($card)->byAuthor($owner)->create();

    $this->actingAs($stranger, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments/{$comment->id}", [
            'body' => 'nope',
        ])
        ->assertNotFound();

    expect($comment->fresh()->body)->not->toBe('nope');
});

it('returns 404 when fetching a comment via a card that belongs to a different card on the same column', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $cardA = KanbanCard::factory()->forColumn($column)->create();
    $cardB = KanbanCard::factory()->forColumn($column)->create();
    $comment = KanbanComment::factory()->forCard($cardA)->byAuthor($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$cardB->id}/comments/{$comment->id}")
        ->assertNotFound();
});

it('exposes the resource shape with id, card_id, author_id, parent_id, body, timestamps', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();
    $column = KanbanColumn::factory()->forBoard($board)->create();
    $card = KanbanCard::factory()->forColumn($column)->create();
    $comment = KanbanComment::factory()->forCard($card)->byAuthor($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}/columns/{$column->id}/cards/{$card->id}/comments/{$comment->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'card_id',
                'author_id',
                'parent_id',
                'body',
                'created_at',
                'updated_at',
            ],
        ]);
});
