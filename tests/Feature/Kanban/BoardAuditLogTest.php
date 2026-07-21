<?php

declare(strict_types=1);

use App\Models\KanbanBoard;
use App\Models\KanbanBoardAuditLog;
use App\Models\Project;
use App\Models\User;

it('returns paginated audit log newest first', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create(['name' => 'Audit Board']);

    $this->actingAs($owner, 'sanctum');

    // Seed three audit rows via the real writer path. Each call toggles the
    // board's archived_at and writes a distinct action row (archive is the
    // first; the next call unarchives; the third re-archives).
    $this->postJson(kanbanPrefix($project)."/boards/{$board->id}/archive")
        ->assertOk();

    $this->postJson(kanbanPrefix($project)."/boards/{$board->id}/archive")
        ->assertOk();

    $this->postJson(kanbanPrefix($project)."/boards/{$board->id}/archive")
        ->assertOk();

    $response = $this->getJson(kanbanPrefix($project)."/boards/{$board->id}/audit")
        ->assertOk();

    // Laravel standard pagination envelope (data + links + meta).
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'board_id', 'actor_user_id', 'action', 'payload', 'created_at'],
        ],
        'links',
        'meta',
    ]);

    $payload = $response->json('data');
    expect($payload)->toHaveCount(3);

    // Newest first: latest toggle wins. With 3 toggles starting from
    // archived_at=null: archived → unarchived → archived.
    expect($payload[0]['action'])->toBe('archived');
    expect($payload[1]['action'])->toBe('unarchived');
    expect($payload[2]['action'])->toBe('archived');

    // created_at is monotonically non-increasing.
    $timestamps = array_map(fn (array $row): string => (string) $row['created_at'], $payload);
    $sorted = $timestamps;
    rsort($sorted, SORT_STRING);
    expect($timestamps)->toBe($sorted);

    foreach ($payload as $row) {
        expect($row['board_id'])->toBe($board->id);
        expect($row['actor_user_id'])->toBe($owner->id);
    }
});

it('paginates with 25 entries per page', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();

    KanbanBoardAuditLog::factory()
        ->forBoard($board)
        ->byActor($owner)
        ->count(30)
        ->create();

    $page1 = $this->actingAs($owner, 'sanctum')
        ->getJson(kanbanPrefix($project)."/boards/{$board->id}/audit?page=1")
        ->assertOk();

    expect($page1->json('data'))->toHaveCount(25);
    expect($page1->json('meta.last_page'))->toBeGreaterThanOrEqual(2);
    expect($page1->json('meta.total'))->toBeGreaterThanOrEqual(30);

    $page2 = $this->actingAs($owner, 'sanctum')
        ->getJson(kanbanPrefix($project)."/boards/{$board->id}/audit?page=2")
        ->assertOk();

    expect($page2->json('data'))->toHaveCount(5);

    // Pages must not overlap.
    $page1Ids = array_map(fn (array $row): int => (int) $row['id'], $page1->json('data'));
    $page2Ids = array_map(fn (array $row): int => (int) $row['id'], $page2->json('data'));
    expect(array_intersect($page1Ids, $page2Ids))->toBe([]);
});

it('returns 404 when board belongs to a different owner', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();

    KanbanBoardAuditLog::factory()
        ->forBoard($board)
        ->byActor($owner)
        ->count(2)
        ->create();

    // 404, never 403 — matches the cross-owner convention.
    $this->actingAs($stranger, 'sanctum')
        ->getJson(kanbanPrefix($project)."/boards/{$board->id}/audit")
        ->assertNotFound();
});

it('cascades audit rows with board force-delete', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();

    KanbanBoardAuditLog::factory()
        ->forBoard($board)
        ->byActor($owner)
        ->count(3)
        ->create();

    expect(KanbanBoardAuditLog::query()->where('board_id', $board->id)->count())->toBe(3);

    // Force-delete the board (bypassing SoftDeletes) — the FK cascade must
    // remove every audit row that referenced this board.
    KanbanBoard::query()->withTrashed()->whereKey($board->id)->forceDelete();

    expect(KanbanBoardAuditLog::query()->where('board_id', $board->id)->count())->toBe(0);
});

it('records audit on clone for both source and new board', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $source = KanbanBoard::factory()->forProject($project)->create(['name' => 'Source Board']);

    // Pre-existing audit on the source.
    KanbanBoardAuditLog::factory()
        ->forBoard($source)
        ->byActor($owner)
        ->withAction('renamed')
        ->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson(kanbanPrefix($project)."/boards/{$source->id}/clone")
        ->assertCreated();

    $newId = (int) $response->json('data.id');
    expect($newId)->not->toBe($source->id);

    // Source board audit log: the clone row references the new board.
    $sourceCloneRow = KanbanBoardAuditLog::query()
        ->where('board_id', $source->id)
        ->where('action', 'cloned')
        ->first();

    expect($sourceCloneRow)->not->toBeNull();
    expect($sourceCloneRow->payload)->toMatchArray([
        'source_board_id' => $source->id,
        'new_board_id' => $newId,
    ]);

    // New board audit log: also a clone row that references the source.
    $newCloneRow = KanbanBoardAuditLog::query()
        ->where('board_id', $newId)
        ->where('action', 'cloned')
        ->first();

    expect($newCloneRow)->not->toBeNull();
    expect($newCloneRow->payload)->toMatchArray([
        'source_board_id' => $source->id,
        'new_board_id' => $newId,
    ]);
});
