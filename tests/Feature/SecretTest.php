<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Secret;
use App\Models\User;

beforeEach(function (): void {});

it('returns 401 on every secret endpoint without a bearer token', function (string $method, string $path): void {
    $response = match ($method) {
        'GET' => $this->getJson($path),
        'POST' => $this->postJson($path, []),
        'PATCH' => $this->patchJson($path, []),
        'PUT' => $this->putJson($path, []),
        'DELETE' => $this->deleteJson($path),
    };

    $response->assertUnauthorized();
})->with([
    'index' => ['GET', '/api/v1/projects/1/secrets'],
    'store' => ['POST', '/api/v1/projects/1/secrets'],
    'show' => ['GET', '/api/v1/projects/1/secrets/1'],
    'update' => ['PUT', '/api/v1/projects/1/secrets/1'],
    'destroy' => ['DELETE', '/api/v1/projects/1/secrets/1'],
]);

it('lists secrets scoped to the owned project', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    Secret::factory()->forProject($project)->count(3)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/secrets")
        ->assertOk();

    $ids = collect($response->json('data'))
        ->map(fn (array $envelope) => $envelope['data']['id'] ?? null)
        ->filter()
        ->values()
        ->all();

    expect($ids)->toHaveCount(3);
});

it('does not expose another users secrets on the index', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    Secret::factory()->forProject($project)->create(['key' => 'DB_PASSWORD']);

    $response = $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/secrets")
        ->assertNotFound();

    expect($response->json('data'))->toBeNull();
});

it('returns a paginated 25-per-page list of secrets', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    Secret::factory()->forProject($project)->count(30)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/secrets")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(25);
});

it('creates a secret in an owned project with 201', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/secrets", [
            'key' => 'DB_PASSWORD',
            'value' => 'super-secret-value',
            'description' => 'production postgres',
        ])
        ->assertCreated();

    expect($response->json('data.key'))->toBe('DB_PASSWORD')
        ->and($response->json('data.value'))->toBe('super-secret-value')
        ->and($response->json('data.description'))->toBe('production postgres')
        ->and($response->json('data.project_id'))->toBe($project->id);

    $id = $response->json('data.id');
    $secret = Secret::query()->findOrFail($id);
    expect($secret->value)->toBe('super-secret-value');
});

it('rejects create when key is missing', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/secrets", [
            'value' => 'x',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['key']);
});

it('rejects create when value is missing', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/secrets", [
            'key' => 'TOKEN',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['value']);
});

it('rejects create when key contains invalid characters', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/secrets", [
            'key' => 'has spaces',
            'value' => 'x',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['key']);
});

it('rejects create when key is longer than 100 chars', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/secrets", [
            'key' => str_repeat('a', 101),
            'value' => 'x',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['key']);
});

it('rejects duplicate keys within the same project (case-insensitive)', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    Secret::factory()->forProject($project)->create(['key' => 'DB_PASSWORD']);

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/secrets", [
            'key' => 'db_password',
            'value' => 'second',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['key']);
});

it('allows the same key in different projects of the same owner', function (): void {
    $owner = User::factory()->create();
    $projectA = Project::factory()->forOwner($owner)->create();
    $projectB = Project::factory()->forOwner($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$projectA->id}/secrets", [
            'key' => 'TOKEN',
            'value' => 'aaa',
        ])->assertCreated();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$projectB->id}/secrets", [
            'key' => 'TOKEN',
            'value' => 'bbb',
        ])->assertCreated();
});

it('description is optional', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/secrets", [
            'key' => 'API_KEY',
            'value' => 'xyz',
        ])
        ->assertCreated();

    expect($response->json('data.description'))->toBeNull();
});

it('shows a secret to the project owner with its decrypted value', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $secret = Secret::factory()->forProject($project)->create([
        'key' => 'API_KEY',
        'value' => 'plain-text-value',
    ]);

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/secrets/{$secret->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $secret->id)
        ->assertJsonPath('data.key', 'API_KEY')
        ->assertJsonPath('data.value', 'plain-text-value');
});

it('returns 404 when a non-owner fetches a secret (no existence leak)', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $secret = Secret::factory()->forProject($project)->create();

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/secrets/{$secret->id}")
        ->assertNotFound();
});

it('returns 404 when fetching an unknown secret id', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/secrets/999999")
        ->assertNotFound();
});

it('updates a secret value and description for the project owner', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $secret = Secret::factory()->forProject($project)->create([
        'key' => 'API_KEY',
        'value' => 'old',
    ]);

    $this->actingAs($owner, 'sanctum')
        ->putJson("/api/v1/projects/{$project->id}/secrets/{$secret->id}", [
            'value' => 'new-value',
            'description' => 'updated',
        ])
        ->assertOk()
        ->assertJsonPath('data.value', 'new-value')
        ->assertJsonPath('data.description', 'updated');

    $fresh = $secret->fresh();
    expect($fresh->value)->toBe('new-value')
        ->and($fresh->description)->toBe('updated');
});

it('returns 404 when a non-owner updates a secret', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $secret = Secret::factory()->forProject($project)->create(['value' => 'old']);

    $this->actingAs($stranger, 'sanctum')
        ->putJson("/api/v1/projects/{$project->id}/secrets/{$secret->id}", [
            'value' => 'hijacked',
        ])
        ->assertNotFound();

    expect($secret->fresh()->value)->toBe('old');
});

it('allows updating only the description without sending the value', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $secret = Secret::factory()->forProject($project)->create([
        'value' => 'original',
    ]);

    $this->actingAs($owner, 'sanctum')
        ->putJson("/api/v1/projects/{$project->id}/secrets/{$secret->id}", [
            'description' => 'note-only update',
        ])
        ->assertOk()
        ->assertJsonPath('data.description', 'note-only update');

    expect($secret->fresh()->value)->toBe('original');
});

it('rejects update with value shorter than 1 char', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $secret = Secret::factory()->forProject($project)->create();

    $this->actingAs($owner, 'sanctum')
        ->putJson("/api/v1/projects/{$project->id}/secrets/{$secret->id}", [
            'value' => '',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['value']);
});

it('deletes a secret for the project owner with 204', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $secret = Secret::factory()->forProject($project)->create();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/secrets/{$secret->id}")
        ->assertNoContent();

    expect(Secret::query()->whereKey($secret->id)->exists())->toBeFalse();
});

it('returns 404 when a non-owner deletes a secret', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $secret = Secret::factory()->forProject($project)->create();

    $this->actingAs($stranger, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/secrets/{$secret->id}")
        ->assertNotFound();

    expect(Secret::query()->whereKey($secret->id)->exists())->toBeTrue();
});

it('returns 404 when a secret belongs to a different project of the same user', function (): void {
    $owner = User::factory()->create();
    $projectA = Project::factory()->forOwner($owner)->create();
    $projectB = Project::factory()->forOwner($owner)->create();
    $secret = Secret::factory()->forProject($projectA)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$projectB->id}/secrets/{$secret->id}")
        ->assertNotFound();
});

it('persists secret values encrypted at rest in the database', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/secrets", [
            'key' => 'DB_PASSWORD',
            'value' => 'clear-text-plaintext',
        ])
        ->assertCreated();

    $raw = DB::table('secrets')->where('project_id', $project->id)->value('value');

    expect($raw)->toBeString()
        ->and($raw)->not->toContain('clear-text-plaintext')
        ->and($raw)->not->toBe('clear-text-plaintext');
});

it('returns 404 cross-owner attack: stranger project id with target secret id', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $secret = Secret::factory()->forProject($project)->create();
    $strangerProject = Project::factory()->forOwner($stranger)->create();

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/projects/{$strangerProject->id}/secrets/{$secret->id}")
        ->assertNotFound();
});

it('exposes the resource shape with id, project_id, key, value, description, timestamps', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $secret = Secret::factory()->forProject($project)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/secrets/{$secret->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'project_id',
                'key',
                'value',
                'description',
                'created_at',
                'updated_at',
            ],
        ]);
});

it('cascades deletion of a project to its secrets', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $secret = Secret::factory()->forProject($project)->create();

    expect(Secret::query()->whereKey($secret->id)->exists())->toBeTrue();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}")
        ->assertNoContent();

    expect(Secret::query()->whereKey($secret->id)->exists())->toBeFalse();
});

it('rejects description longer than 1000 chars on store', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/secrets", [
            'key' => 'X',
            'value' => 'v',
            'description' => str_repeat('a', 1001),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['description']);
});

it('rejects value longer than 8192 chars on store', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/secrets", [
            'key' => 'X',
            'value' => str_repeat('a', 8193),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['value']);
});
