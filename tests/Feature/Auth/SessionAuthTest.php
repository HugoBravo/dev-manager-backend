<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs in a valid user, starts a session, and returns the user payload', function (): void {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->withHeaders(['Origin' => 'http://localhost:4200'])
        ->postJson('/api/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'password',
            'device_name' => 'spa',
        ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['id', 'name', 'email']]);

    $cookies = $response->headers->getCookies();
    $hasSessionCookie = false;
    foreach ($cookies as $cookie) {
        if (in_array($cookie->getName(), ['laravel_session', 'XSRF-TOKEN'], true)) {
            $hasSessionCookie = true;
            break;
        }
    }
    expect($hasSessionCookie)->toBeTrue();
});

it('rejects login with invalid credentials', function (): void {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->withHeaders(['Origin' => 'http://localhost:4200'])
        ->postJson('/api/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);

    $response->assertStatus(422);
});

it('logs out an authenticated session user and clears the session cookie', function (): void {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user, 'web');

    $response = $this->withHeaders(['Origin' => 'http://localhost:4200'])
        ->postJson('/api/auth/logout');

    $response->assertNoContent();
    expect(auth()->guard('web')->user())->toBeNull();
});

it('returns 401 when accessing /api/user while unauthenticated', function (): void {
    $this->getJson('/api/user')->assertUnauthorized();
});
