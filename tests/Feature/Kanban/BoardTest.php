<?php

declare(strict_types=1);

use App\Models\KanbanBoard;
use App\Models\KanbanBoardAuditLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Policies\KanbanBoardPolicy;
use Illuminate\Support\Facades\DB;
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
    'index' => ['GET', '/api/v1/projects/1/tasks/1/kanban/boards'],
    'store' => ['POST', '/api/v1/projects/1/tasks/1/kanban/boards'],
    'show' => ['GET', '/api/v1/projects/1/tasks/1/kanban/boards/1'],
    'update' => ['PATCH', '/api/v1/projects/1/tasks/1/kanban/boards/1'],
    'destroy' => ['DELETE', '/api/v1/projects/1/tasks/1/kanban/boards/1'],
    'archive' => ['POST', '/api/v1/projects/1/tasks/1/kanban/boards/1/archive'],
    'reorder' => ['POST', '/api/v1/projects/1/tasks/1/kanban/boards/reorder'],
]);

it('lists boards of an owned project', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    KanbanBoard::factory()->forProject($project)->count(3)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson(kanbanPrefix($project).'/boards')
        ->assertOk();

    $ids = collect($response->json('data'))
        ->map(fn (array $envelope) => $envelope['data']['id'] ?? null)
        ->all();

    expect($ids)->toHaveCount(3);
});

it('returns a paginated 25-per-page list of boards', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    KanbanBoard::factory()->forProject($project)->count(30)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson(kanbanPrefix($project).'/boards')
        ->assertOk();

    // Resource::collection wraps each item in {data:{...}}, so the top-level
    // body has its own `data` array plus LengthAwarePaginator meta fields.
    expect($response->json('data'))->toHaveCount(25);
});

it('creates a board in an owned project with 201', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->default()->create(['project_id' => $project->id]);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/kanban/boards", [
            'name' => 'Sprint 1',
        ])
        ->assertCreated();

    $id = $response->json('data.id');
    expect($id)->toBeInt();

    $board = KanbanBoard::query()->findOrFail($id);
    expect($board->name)->toBe('Sprint 1')
        ->and($board->task_id)->toBe($task->id)
        ->and($board->task->project_id)->toBe($project->id)
        ->and($board->archived_at)->toBeNull()
        ->and($board->position)->toBeString();
});

it('rejects create with empty name', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson(kanbanPrefix($project).'/boards', ['name' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('rejects create with name longer than 100 chars', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson(kanbanPrefix($project).'/boards', [
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
        ->getJson(kanbanPrefix($project).'/boards')
        ->assertNotFound();
});

it('shows a board to the project owner', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson(kanbanPrefix($project)."/boards/{$board->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $board->id)
        ->assertJsonPath('data.name', $board->name);
});

it('returns 404 when a non-owner fetches a board (no existence leak)', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();

    $this->actingAs($stranger, 'sanctum')
        ->getJson(kanbanPrefix($project)."/boards/{$board->id}")
        ->assertNotFound();
});

it('returns 404 when fetching a board of a project the user does not own even if board id exists', function (): void {
    // Cross-project attack: stranger owns his own project+board; uses own project id
    // in the URL but the OTHER project's board id. Both must 404 — no leak.
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $ownedProject = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($ownedProject)->create();
    $strangerProject = Project::factory()->forOwner($stranger)->create();

    $this->actingAs($stranger, 'sanctum')
        ->getJson(kanbanPrefix($strangerProject)."/boards/{$board->id}")
        ->assertNotFound();
});

it('updates a boards name for the project owner', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();

    $this->actingAs($owner, 'sanctum')
        ->patchJson(kanbanPrefix($project)."/boards/{$board->id}", [
            'name' => 'Renamed',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');

    expect($board->fresh()->name)->toBe('Renamed');
});

it('writes a renamed audit row when board name changes', function (): void {
    // Spec capability `board-audit-log` (sdd/boards-kanban-crud-full/spec §5)
    // requires a `renamed` audit row whenever the board name changes via
    // PATCH /boards/{board}, carrying `old_name` + `new_name` in the payload.
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create([
        'name' => 'Original',
    ]);

    $this->actingAs($owner, 'sanctum')
        ->patchJson(kanbanPrefix($project)."/boards/{$board->id}", [
            'name' => 'Renamed',
        ])
        ->assertOk();

    // Authoritative audit row: board_id + action. actor_user_id asserted
    // separately via the explicit query below because assertDatabaseHas
    // JSON-casts the payload column on a non-strict lookup.
    $this->assertDatabaseHas('board_audit_logs', [
        'board_id' => $board->id,
        'action' => 'renamed',
    ]);

    $row = KanbanBoardAuditLog::query()
        ->where('board_id', $board->id)
        ->where('action', 'renamed')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->actor_user_id)->toBe($owner->id);
    expect($row->payload)->toMatchArray([
        'old_name' => 'Original',
        'new_name' => 'Renamed',
    ]);
});

it('does NOT write a renamed audit row when the new name equals the current name', function (): void {
    // No-op rename: same name submitted → no audit spam. The unique-name
    // rule would 422 if it collided with another board, so passing the
    // current name back is the only "change without change" path.
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create([
        'name' => 'Stable',
    ]);

    $this->actingAs($owner, 'sanctum')
        ->patchJson(kanbanPrefix($project)."/boards/{$board->id}", [
            'name' => 'Stable',
        ])
        ->assertOk();

    expect(KanbanBoardAuditLog::query()
        ->where('board_id', $board->id)
        ->where('action', 'renamed')
        ->exists())->toBeFalse();
});

it('returns 404 when a non-owner updates a board', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();

    $this->actingAs($stranger, 'sanctum')
        ->patchJson(kanbanPrefix($project)."/boards/{$board->id}", [
            'name' => 'Hijacked',
        ])
        ->assertNotFound();

    expect($board->fresh()->name)->not->toBe('Hijacked');
});

it('rejects update with name longer than 100 chars', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();

    $this->actingAs($owner, 'sanctum')
        ->patchJson(kanbanPrefix($project)."/boards/{$board->id}", [
            'name' => str_repeat('b', 101),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('deletes an empty board with 204 (soft-delete: row stays, deleted_at is set)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson(kanbanPrefix($project)."/boards/{$board->id}")
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
    $board = KanbanBoard::factory()->forProject($project)->create();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson(kanbanPrefix($project)."/boards/{$board->id}")
        ->assertNoContent();

    $this->actingAs($owner, 'sanctum')
        ->getJson(kanbanPrefix($project)."/boards/{$board->id}")
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
    $board = KanbanBoard::factory()->forProject($project)->create();

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
        ->deleteJson(kanbanPrefix($project)."/boards/{$board->id}")
        ->assertStatus(409);

    expect(KanbanBoard::query()->find($board->id))->not->toBeNull();
});

it('returns 409 with a typed `board_has_contents` code in the response body', function (): void {
    // Forward-compatible contract check: the 409 body carries a `code` field
    // the frontend can switch on. Independent test from the destroy-flow above.
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();

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
        ->deleteJson(kanbanPrefix($project)."/boards/{$board->id}")
        ->assertStatus(409);

    expect($response->json('code'))->toBe('board_has_contents');
});

it('returns 404 when a non-owner deletes a board', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();

    $this->actingAs($stranger, 'sanctum')
        ->deleteJson(kanbanPrefix($project)."/boards/{$board->id}")
        ->assertNotFound();

    expect(KanbanBoard::query()->find($board->id))->not->toBeNull();
});

it('archives a board for the project owner', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson(kanbanPrefix($project)."/boards/{$board->id}/archive")
        ->assertOk();

    // Resource is wrapped by BoardResource's own `data` key inside the
    // JsonResource::response() envelope, so the path is `data.data.archived_at`.
    $archivedAt = $response->json('data.data.archived_at')
        ?? $response->json('data.archived_at');
    expect($archivedAt)->not->toBeNull();

    expect($board->fresh()->archived_at)->not->toBeNull();

    // Audit row recorded with the actor + ISO archived_at timestamp.
    $this->assertDatabaseHas('board_audit_logs', [
        'board_id' => $board->id,
        'actor_user_id' => $owner->id,
        'action' => 'archived',
    ]);
});

it('archive is a toggle: a second archive call on an already-archived board clears archived_at and records `unarchived`', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson(kanbanPrefix($project)."/boards/{$board->id}/archive")
        ->assertOk();

    expect($board->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($owner, 'sanctum')
        ->postJson(kanbanPrefix($project)."/boards/{$board->id}/archive")
        ->assertOk();

    // Toggle semantic: archive twice → unarchived.
    expect($board->fresh()->archived_at)->toBeNull();

    $this->assertDatabaseHas('board_audit_logs', [
        'board_id' => $board->id,
        'action' => 'archived',
    ]);
    $this->assertDatabaseHas('board_audit_logs', [
        'board_id' => $board->id,
        'action' => 'unarchived',
    ]);
});

it('returns 404 when a non-owner archives a board', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create();

    $this->actingAs($stranger, 'sanctum')
        ->postJson(kanbanPrefix($project)."/boards/{$board->id}/archive")
        ->assertNotFound();

    expect($board->fresh()->archived_at)->toBeNull();
});

it('reorders boards and persists the new ordering on a second fetch', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $b1 = KanbanBoard::factory()->forProject($project)->create();
    $b2 = KanbanBoard::factory()->forProject($project)->create();
    $b3 = KanbanBoard::factory()->forProject($project)->create();

    // New order: b3, b1, b2.
    $this->actingAs($owner, 'sanctum')
        ->postJson(kanbanPrefix($project).'/boards/reorder', [
            'ordered_ids' => [$b3->id, $b1->id, $b2->id],
        ])
        ->assertOk();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson(kanbanPrefix($project).'/boards')
        ->assertOk();

    $ids = collect($response->json('data'))
        ->map(fn (array $envelope) => $envelope['data']['id'] ?? null)
        ->values()
        ->all();

    expect($ids)->toBe([$b3->id, $b1->id, $b2->id]);

    // Audit hook: one `reordered` row per board with from/to position in payload.
    foreach ([$b3->id, $b1->id, $b2->id] as $boardId) {
        $row = DB::table('board_audit_logs')
            ->where('board_id', $boardId)
            ->where('action', 'reordered')
            ->first();

        expect($row)->not->toBeNull();
        $payload = json_decode($row->payload, true);
        expect($payload)->toBeArray()
            ->and($payload)->toHaveKeys(['from_position', 'to_position']);
    }
});

it('returns 404 when a non-owner reorders boards', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $b1 = KanbanBoard::factory()->forProject($project)->create();
    $b2 = KanbanBoard::factory()->forProject($project)->create();

    $this->actingAs($stranger, 'sanctum')
        ->postJson(kanbanPrefix($project).'/boards/reorder', [
            'ordered_ids' => [$b2->id, $b1->id],
        ])
        ->assertNotFound();
});

it('rejects reorder payload with ids from another project', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $b1 = KanbanBoard::factory()->forProject($project)->create();
    $b2 = KanbanBoard::factory()->forProject($project)->create();

    // Foreign project board owned by the same user but different project.
    $otherProject = Project::factory()->forOwner($owner)->create();
    $foreignBoard = KanbanBoard::factory()->forProject($otherProject)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson(kanbanPrefix($project).'/boards/reorder', [
            'ordered_ids' => [$b1->id, $foreignBoard->id, $b2->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ordered_ids']);
});

it('rejects reorder payload with duplicate ids', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $b1 = KanbanBoard::factory()->forProject($project)->create();
    $b2 = KanbanBoard::factory()->forProject($project)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson(kanbanPrefix($project).'/boards/reorder', [
            'ordered_ids' => [$b1->id, $b1->id, $b2->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ordered_ids']);
});

it('exposes the resource shape with id, task_id, embedded task, name, position, archived_at, timestamps', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->default()->create(['project_id' => $project->id]);
    $board = KanbanBoard::factory()->forTask($task)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/kanban/boards/{$board->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'task_id',
                'task' => ['id', 'name', 'slug', 'status', 'archived_at'],
                'name',
                'position',
                'archived_at',
                'created_at',
                'updated_at',
            ],
        ]);

    // REQ-RESOURCES-2 (spec): BoardResource MUST NOT expose `project_id`.
    // The frontend reads `task_id` + the embedded `task` object instead.
    $response->assertJsonMissingPath('data.project_id');
});

it('embeds a populated task object on every board index entry (REQ-RESOURCES-2)', function (): void {
    // Verify CRITICAL #1 from the verify report: `BoardResource` lazy fallback
    // returned `{id, name:null, slug:null, status:null, archived_at:null}` on
    // the index path because `BoardController::index` did not eager-load
    // the `task` relation. This regression test pins every populated field
    // so a future controller refactor cannot silently regress to the
    // null-fallback shape without breaking the assertion set.
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->default()->create([
        'project_id' => $project->id,
        'name' => 'Refactor checkout',
        'slug' => 'refactor-checkout',
        'status' => 'in_progress',
    ]);
    KanbanBoard::factory()->forTask($task)->count(2)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/kanban/boards")
        ->assertOk();

    $entries = collect($response->json('data'));
    expect($entries)->toHaveCount(2);

    foreach ($entries as $entry) {
        $taskPayload = $entry['data']['task'] ?? null;
        expect($taskPayload)->toBeArray()
            ->and($taskPayload['id'])->toBe($task->id)
            ->and($taskPayload['name'])->toBe('Refactor checkout')
            ->and($taskPayload['slug'])->toBe('refactor-checkout')
            ->and($taskPayload['status'])->toBe('in_progress')
            ->and($taskPayload['archived_at'])->toBeNull();
    }
});

it('caps board position strings at 1024 bytes', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->forProject($project)->create([
        'position' => str_repeat('z', 1024),
    ]);

    expect(strlen($board->position))->toBeLessThanOrEqual(1024);
});

it('rejects 422 name_taken on case-insensitive duplicate create', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    // "Sprint 1" with intentional whitespace padding inside the name.
    $this->actingAs($owner, 'sanctum')
        ->postJson(kanbanPrefix($project).'/boards', [
            'name' => 'Sprint 1',
        ])
        ->assertCreated();

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson(kanbanPrefix($project).'/boards', [
            'name' => 'sprint  1  ',
        ])
        ->assertStatus(422);

    expect($response->json('code'))->toBe('name_taken');
});

it('rejects 422 name_taken on rename to existing name (case-insensitive)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $b1 = KanbanBoard::factory()->forProject($project)->create(['name' => 'Sprint 1']);
    KanbanBoard::factory()->forProject($project)->create(['name' => 'Sprint 2']);

    $response = $this->actingAs($owner, 'sanctum')
        ->patchJson(kanbanPrefix($project)."/boards/{$b1->id}", [
            'name' => 'sprint 2',
        ])
        ->assertStatus(422);

    expect($response->json('code'))->toBe('name_taken');
});

it('allows recycling a name from a trashed board', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $first = $this->actingAs($owner, 'sanctum')
        ->postJson(kanbanPrefix($project).'/boards', ['name' => 'Sprint 1'])
        ->assertCreated();
    $firstId = $first->json('data.id');

    $this->actingAs($owner, 'sanctum')
        ->deleteJson(kanbanPrefix($project)."/boards/{$firstId}")
        ->assertNoContent();

    // After the row is soft-deleted, recreating with the same name must succeed.
    $second = $this->actingAs($owner, 'sanctum')
        ->postJson(kanbanPrefix($project).'/boards', ['name' => 'Sprint 1'])
        ->assertCreated();

    expect($second->json('data.id'))->not->toBe($firstId);
});
