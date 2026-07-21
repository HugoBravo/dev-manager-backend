<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('does not register statefulApi() — no Set-Cookie on login from allowed origin', function (): void {
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

    $response->assertOk();

    $hasSessionCookie = collect($response->headers->getCookies())
        ->contains(fn ($cookie) => in_array($cookie->getName(), ['laravel_session', 'XSRF-TOKEN'], true));

    expect($hasSessionCookie)->toBeFalse();
});

it('issues a bearer token on valid login and returns flat envelope', function (): void {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'password',
        'device_name' => 'angular-dev',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'user' => ['data' => ['id', 'name', 'email', 'email_verified_at']],
            'token',
        ]);

    expect($response->json('token'))
        ->toBeString()
        ->toMatch('/^\d+\|[A-Za-z0-9]+$/');

    expect($response->headers->getCookies())
        ->not->toContain(fn ($cookie) => $cookie->getName() === 'laravel_session');
});

it('rejects login with invalid credentials and returns errors.email', function (): void {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('returns 401 when accessing /api/user without bearer', function (): void {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('returns 401 when accessing /api/user with malformed bearer', function (): void {
    $this->withToken('abc-not-a-real-token')
        ->getJson('/api/user')
        ->assertUnauthorized();
});

it('returns the canonical user resource with a strict boolean admin role', function (bool $isAdmin): void {
    $user = User::factory()->create(['is_admin' => $isAdmin]);

    $response = $this->withToken(bearerFor($user))
        ->getJson('/api/user')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'is_admin' => $isAdmin,
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
        ]);

    expect($response->json('data.is_admin'))
        ->toBe($isAdmin)
        ->toBeBool();
})->with([
    'admin' => [true],
    'non-admin' => [false],
]);

it('logs out via bearer token and returns 204', function (): void {
    $user = User::factory()->create();
    $token = bearerFor($user);

    $response = $this->withToken($token)
        ->postJson('/api/auth/logout');

    $response->assertNoContent();
    expect($user->fresh()->tokens)->toHaveCount(0);
});

it('returns 401 on subsequent /api/user with revoked token', function (): void {
    $user = User::factory()->create();
    $token = bearerFor($user);
    [$id] = explode('|', $token, 2);

    $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

    // Sanctum's first-checks-web-guard behavior persists the user across
    // requests within a single test method (RefreshDatabase keeps the test
    // client alive). In production, with statefulApi() removed, the web
    // guard has no session user. Here we forget guards to simulate that.
    auth()->forgetGuards();

    expect(PersonalAccessToken::findToken($token))->toBeNull();

    $this->withToken($token)
        ->getJson('/api/user')
        ->assertUnauthorized();
});

it('returns 401 when calling /api/auth/logout without bearer', function (): void {
    $this->postJson('/api/auth/logout')->assertUnauthorized();
});

it('blocks login of a soft-deleted user with the correct credentials (S14)', function (): void {
    $user = User::factory()->create([
        'email' => 'ghost@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->delete();

    $response = $this->postJson('/api/auth/login', [
        'email' => 'ghost@example.com',
        'password' => 'password',
        'device_name' => 'phpunit',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('enforces throttle on /api/auth/login', function (): void {
    RateLimiter::clear('login:127.0.0.1');
    RateLimiter::clear('login:::1');

    $payload = [
        'email' => 'nobody@example.com',
        'password' => 'wrong-password',
    ];

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/auth/login', $payload);
    }

    $blocked = $this->postJson('/api/auth/login', $payload);
    expect($blocked->getStatusCode())->toBe(429);
});
