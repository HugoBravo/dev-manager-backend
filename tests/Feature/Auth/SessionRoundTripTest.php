<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('completes a bearer-token round trip: register → user → logout → revoked', function (): void {
    $registerResponse = $this->postJson('/api/auth/register', [
        'name' => 'Jane Demo',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'spa',
    ])->assertStatus(201);

    $token = $registerResponse->json('token');
    expect($token)->toBeString()->toMatch('/^\d+\|[A-Za-z0-9]+$/');

    $this->withToken($token)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.email', 'jane@example.com');

    $this->withToken($token)
        ->postJson('/api/auth/logout')
        ->assertNoContent();

    // Forget guards so the next request resolves only via the bearer token.
    // In production, with statefulApi() removed, the web guard has no user.
    auth()->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/user')
        ->assertUnauthorized();
});

it('issues bearer token usable against /api/user', function (): void {
    $user = User::factory()->create();
    $plain = bearerFor($user);

    $this->withToken($plain)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});
