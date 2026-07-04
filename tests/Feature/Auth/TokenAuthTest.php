<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('issues a personal access token for valid credentials', function (): void {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/auth/token', [
        'email' => 'jane@example.com',
        'password' => 'password',
        'device_name' => 'cli',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'abilities']);

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});

it('rejects token issuance with invalid credentials', function (): void {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/auth/token', [
        'email' => 'jane@example.com',
        'password' => 'wrong',
    ]);

    $response->assertStatus(422);
});

it('revokes an existing token when authenticated', function (): void {
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
