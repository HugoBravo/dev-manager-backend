<?php

declare(strict_types=1);

use App\Models\KanbanBoard;
use App\Models\Project;
use App\Models\User;

it('soft-deletes an empty board and excludes it from default index', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $board = KanbanBoard::factory()->for($project, 'project')->create();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/kanban/boards/{$board->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('kanban_boards', ['id' => $board->id]);

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kanban/boards")
        ->assertOk();

    $ids = collect($response->json('data'))
        ->map(fn (array $envelope): mixed => $envelope['data']['id'] ?? null)
        ->all();

    expect($ids)->not->toContain($board->id);
});
