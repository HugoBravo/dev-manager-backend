<?php

declare(strict_types=1);

use App\Models\KanbanBoard;
use App\Models\KanbanBoardAuditLog;
use App\Models\Project;
use App\Models\User;
use App\Services\Kanban\BoardAuditLogger;

it('records an entry with action, actor and payload', function (): void {
    $actor = User::factory()->create();
    $project = Project::factory()->forOwner($actor)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $this->actingAs($actor, 'sanctum');

    $entry = app(BoardAuditLogger::class)->record($board, 'created', ['k' => 'v']);

    expect($entry)->toBeInstanceOf(KanbanBoardAuditLog::class);

    $this->assertDatabaseHas('board_audit_logs', [
        'id' => $entry->id,
        'board_id' => $board->id,
        'actor_user_id' => $actor->id,
        'action' => 'created',
    ]);

    $persisted = KanbanBoardAuditLog::query()->findOrFail($entry->id);
    expect($persisted->payload)->toBe(['k' => 'v']);
});
