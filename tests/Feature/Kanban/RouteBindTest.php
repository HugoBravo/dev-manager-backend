<?php

declare(strict_types=1);

use App\Models\KanbanBoard;
use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

/**
 * Regression suite for the kanban route-bind fix (`fix/kanban-route-bind-int-arg`).
 *
 * The original controllers declared `int $project` while the kanban routes
 * accept `{project}` as a URI segment. When the front-end hit
 * `/api/v1/projects/{slug}/kanban/...` with a non-numeric slug, Laravel
 * handed the raw string to the controller and it threw `TypeError`.
 *
 * The fix is a `Route::bind('project', ...)` closure in AppServiceProvider
 * that resolves by id when numeric and by slug otherwise, plus controller
 * signatures changed from `int $project` to `Project $project`. These
 * tests pin BOTH URL formats so a future refactor cannot silently regress.
 */
it('returns the project board list with a slug-keyed URL', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    KanbanBoard::factory()->forProject($project)->create(['name' => 'Slug Board']);

    Sanctum::actingAs($owner);

    getJson(kanbanPrefix($project, $project->slug).'/boards')
        ->assertOk()
        ->assertJsonPath('data.0.data.name', 'Slug Board');
});

it('still returns the project board list with an id-keyed URL (legacy callers)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    KanbanBoard::factory()->forProject($project)->create(['name' => 'Id Board']);

    Sanctum::actingAs($owner);

    getJson(kanbanPrefix($project).'/boards')
        ->assertOk()
        ->assertJsonPath('data.0.data.name', 'Id Board');
});

it('returns 404 on a stranger-owned slug (no existence leak)', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    Sanctum::actingAs($stranger);

    getJson(kanbanPrefix($project, $project->slug).'/boards')
        ->assertNotFound();
});

it('returns 404 on an unknown slug (no existence leak)', function (): void {
    $owner = User::factory()->create();

    Sanctum::actingAs($owner);

    getJson('/api/v1/projects/totally-not-a-real-slug/tasks/1/kanban/boards')
        ->assertNotFound();
});

it('shows a project by slug (single-resource route regression)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create(['name' => 'Demo Project']);

    Sanctum::actingAs($owner);

    getJson("/api/v1/projects/{$project->slug}")
        ->assertOk()
        ->assertJsonPath('data.slug', $project->slug);
});
