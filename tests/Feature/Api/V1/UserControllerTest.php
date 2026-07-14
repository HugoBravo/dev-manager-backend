<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function (): void {});

it('returns 401 on every users endpoint without a bearer token', function (string $method, string $path): void {
    $response = match ($method) {
        'GET' => $this->getJson($path),
        'POST' => $this->postJson($path, []),
        'PATCH' => $this->patchJson($path, []),
        'PUT' => $this->putJson($path, []),
        'DELETE' => $this->deleteJson($path),
    };

    $response->assertUnauthorized();
})->with([
    'index' => ['GET', '/api/v1/users'],
    'store' => ['POST', '/api/v1/users'],
    'show' => ['GET', '/api/v1/users/1'],
    'update' => ['PUT', '/api/v1/users/1'],
    'destroy' => ['DELETE', '/api/v1/users/1'],
]);

it('lists users when called by an admin (S1)', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/users')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

it('excludes soft-deleted users from the index (S1)', function (): void {
    $admin = User::factory()->admin()->create();
    $live = User::factory()->create();
    $deleted = User::factory()->create(['deleted_at' => now()]);

    $ids = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/users')
        ->assertOk()
        ->json('data');

    $ids = collect($ids)->map(fn (array $e) => $e['data']['id'])->all();

    expect($ids)->toContain($admin->id, $live->id)
        ->and($ids)->not->toContain($deleted->id);
});

it('forbids listing users for non-admins (S2)', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/users')
        ->assertForbidden();
});

it('lets an admin show any user (S3)', function (): void {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->create();

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/users/{$other->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $other->id)
        ->assertJsonPath('data.email', $other->email);
});

it('lets a non-admin show their own record (S4)', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/users/{$user->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('forbids showing another user to a non-admin (S5)', function (): void {
    $user = User::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/users/{$stranger->id}")
        ->assertForbidden();
});

it('lets an admin create a user with 201 (S6)', function (): void {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/users', [
            'name' => 'New Hire',
            'email' => 'new@example.com',
            'password' => 'password123',
            'is_admin' => false,
        ])
        ->assertCreated();

    expect($response->json('data.id'))->toBeInt()
        ->and($response->json('data.password'))->toBeNull();

    $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
});

it('returns 422 on duplicate email when admin creates a user (S7)', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'dup@example.com']);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/users', [
            'name' => 'Dup',
            'email' => 'dup@example.com',
            'password' => 'password123',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('forbids non-admin creation (cross-user admin gate)', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/users', [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'password123',
        ])
        ->assertForbidden();
});

it('forbids a non-admin from updating another user (S8)', function (): void {
    $user = User::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/users/{$stranger->id}", ['name' => 'Hijacked'])
        ->assertForbidden();
});

it('lets a non-admin update their own record (name + password only) (S9)', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/users/{$user->id}", [
            'name' => 'Renamed Self',
            'password' => 'freshpassword1',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed Self');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Renamed Self',
    ]);
});

it('rejects privilege escalation on self-update with 422 (S10)', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/users/{$user->id}", [
            'email' => 'escalate@example.com',
            'is_admin' => true,
        ])
        ->assertStatus(422);

    $response->assertJsonValidationErrors(['email', 'is_admin']);

    expect($user->fresh()->is_admin)->toBeFalse()
        ->and($user->fresh()->email)->not->toBe('escalate@example.com');
});

it('lets an admin update arbitrary fields (S11)', function (): void {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/users/{$target->id}", [
            'name' => 'New Name',
            'email' => 'newmail@example.com',
            'is_admin' => true,
            'password' => 'newpassword1',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.email', 'newmail@example.com')
        ->assertJsonPath('data.is_admin', true);
});

it('soft-deletes a user and revokes their tokens (S12)', function (): void {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();
    $token = $target->createToken('phpunit');

    expect(PersonalAccessToken::query()->where('tokenable_id', $target->id)->count())->toBe(1);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/users/{$target->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('users', ['id' => $target->id]);
    expect(PersonalAccessToken::query()->where('tokenable_id', $target->id)->count())->toBe(0);
});

it('forbids non-admin deletion (S13)', function (): void {
    $user = User::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/users/{$stranger->id}")
        ->assertForbidden();
});

it('refuses to delete the currently authenticated admin (risk R3)', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/users/{$admin->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user']);

    $this->assertDatabaseHas('users', ['id' => $admin->id, 'deleted_at' => null]);
});
