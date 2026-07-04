<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('issues bearer token for valid credentials via /api/auth/login', function (): void {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'password',
        'device_name' => 'cli',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['user' => ['data'], 'token']);

    expect($response->json('token'))
        ->toBeString()
        ->toMatch('/^\d+\|[A-Za-z0-9]+$/');
});

it('rejects /api/auth/login with invalid credentials', function (): void {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('revokes an existing token when authenticated via DELETE /api/auth/token/{tokenId}', function (): void {
    $user = User::factory()->create();
    $plain = $user->createToken('cli')->plainTextToken;

    $tokenId = $user->tokens()->first()->id;

    $response = $this->withToken($plain)
        ->withHeader('Accept', 'application/json')
        ->deleteJson("/api/auth/token/{$tokenId}");

    $response->assertNoContent();
    expect($user->fresh()->tokens)->toHaveCount(0);
});

it('rejects token revocation when unauthenticated', function (): void {
    $response = $this->deleteJson('/api/auth/token/1');
    $response->assertUnauthorized();
});

it('returns 404 for the removed POST /api/auth/token route (K1)', function (): void {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->postJson('/api/auth/token', [
        'email' => 'jane@example.com',
        'password' => 'password',
        'device_name' => 'cli',
    ])->assertNotFound();
});
