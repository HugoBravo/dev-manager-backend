<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;

beforeEach(function (): void {
    // ProjectTest runs inside Feature/ where RefreshDatabase is wired project-wide
    // via tests/Pest.php line 18-20. Do NOT add the trait here.
});

it('returns 401 when listing projects without a bearer token', function (): void {
    $this->getJson('/api/v1/projects')->assertUnauthorized();
});

it('returns 401 when creating a project without a bearer token', function (): void {
    $this->postJson('/api/v1/projects', ['name' => 'X'])->assertUnauthorized();
});

it('returns 401 when showing a project without a bearer token', function (): void {
    $this->getJson('/api/v1/projects/1')->assertUnauthorized();
});

it('returns 401 when updating a project without a bearer token', function (): void {
    $this->patchJson('/api/v1/projects/1', ['name' => 'X'])->assertUnauthorized();
});

it('returns 401 when deleting a project without a bearer token', function (): void {
    $this->deleteJson('/api/v1/projects/1')->assertUnauthorized();
});

it('returns the authenticated users projects only on index', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $owned = Project::factory()->for($owner, 'owner')->count(2)->create();
    Project::factory()->for($stranger, 'owner')->count(3)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/projects')
        ->assertOk();

    // Resource collection wraps each item under its own {data:...} envelope,
    // so the paginator response shape is: data: [ {data:{id,...}}, ... ], plus
    // links/meta. Flatten to extract the inner ids.
    $items = $response->json('data');
    $ids = collect($items)->map(fn ($item) => $item['data']['id'] ?? null)->all();

    expect($ids)->toEqualCanonicalizing($owned->pluck('id')->all());
});

it('creates a project owned by the authenticated user', function (): void {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/projects', [
            'name' => 'My Project',
            'description' => 'first one',
        ])
        ->assertCreated();

    $id = $response->json('data.id');
    expect($id)->toBeInt();

    $project = Project::query()->findOrFail($id);
    expect($project->owner_id)->toBe($owner->id)
        ->and($project->name)->toBe('My Project')
        ->and($project->description)->toBe('first one');
});

it('rejects create with empty name', function (): void {
    $owner = User::factory()->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/projects', ['name' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('rejects create with name longer than 100 chars', function (): void {
    $owner = User::factory()->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/projects', ['name' => str_repeat('a', 101)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('shows a project to its owner', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $project->id)
        ->assertJsonPath('data.name', $project->name);
});

it('returns 404 when a non-owner fetches a project (no existence leak)', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}")
        ->assertNotFound();
});

it('returns 404 when fetching an unknown project id', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/projects/999999')
        ->assertNotFound();
});

it('updates a project for its owner', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}", [
            'name' => 'Renamed',
            'description' => 'updated',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed')
        ->assertJsonPath('data.description', 'updated');

    expect($project->fresh()->name)->toBe('Renamed');
});

it('returns 404 when a non-owner updates a project', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($stranger, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}", ['name' => 'Hijacked'])
        ->assertNotFound();

    expect($project->fresh()->name)->not->toBe('Hijacked');
});

it('rejects update with name longer than 100 chars', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}", ['name' => str_repeat('b', 101)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('deletes a project for its owner', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}")
        ->assertNoContent();

    expect(Project::query()->find($project->id))->toBeNull();
});

it('returns 404 when a non-owner deletes a project', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($stranger, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}")
        ->assertNotFound();

    expect(Project::query()->find($project->id))->not->toBeNull();
});

it('returns the resource shape with id, name, description, owner_id, slug, archived_at and timestamps', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'description',
                'slug',
                'archived_at',
                'owner_id',
                'created_at',
                'updated_at',
            ],
        ]);
});

it('persists a slug when creating a project with one', function (): void {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/projects', [
            'name' => 'My Project',
            'description' => 'with slug',
            'slug' => 'my-project',
        ])
        ->assertCreated();

    expect($response->json('data.slug'))->toBe('my-project');

    $project = Project::query()->findOrFail($response->json('data.id'));
    expect($project->slug)->toBe('my-project');
});

it('updates the slug when patching a project', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create([
        'slug' => 'original-slug',
    ]);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}", ['slug' => 'renamed-slug'])
        ->assertOk()
        ->assertJsonPath('data.slug', 'renamed-slug');

    expect($project->fresh()->slug)->toBe('renamed-slug');
});

it('excludes archived projects from the index by default', function (): void {
    $owner = User::factory()->create();

    $active = Project::factory()->for($owner, 'owner')->create(['archived_at' => null]);
    $archived = Project::factory()->for($owner, 'owner')->create([
        'archived_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/projects')
        ->assertOk();

    $ids = collect($response->json('data'))->map(fn ($i) => $i['data']['id'])->all();

    expect($ids)->toContain($active->id)
        ->and($ids)->not->toContain($archived->id);
});

it('includes archived projects on the index when ?include_archived=1 is set', function (): void {
    $owner = User::factory()->create();

    $active = Project::factory()->for($owner, 'owner')->create(['archived_at' => null]);
    $archived = Project::factory()->for($owner, 'owner')->create([
        'archived_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/projects?include_archived=1')
        ->assertOk();

    $ids = collect($response->json('data'))->map(fn ($i) => $i['data']['id'])->all();

    expect($ids)->toContain($active->id)
        ->and($ids)->toContain($archived->id);
});

it('sets archived_at when patching a project', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create(['archived_at' => null]);

    expect($project->archived_at)->toBeNull();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}", [
            'archived_at' => now()->toIso8601String(),
        ])
        ->assertOk();

    $fresh = $project->fresh();
    expect($fresh->archived_at)->not->toBeNull();
});
