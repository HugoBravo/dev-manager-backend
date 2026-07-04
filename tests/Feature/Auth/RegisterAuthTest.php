<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    RateLimiter::clear('register:127.0.0.1');
    RateLimiter::clear('register:::1');
});

it('rejects register with missing name', function (): void {
    $response = $this->postJson('/api/auth/register', [
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('rejects register with invalid email format', function (): void {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Demo',
        'email' => 'not-an-email',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('rejects register when password is not confirmed', function (): void {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Demo',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'different-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('rejects register when email already exists', function (): void {
    User::factory()->create([
        'email' => 'jane@example.com',
    ]);

    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Demo',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('registers a new user, returns 201 + envelope + token', function (): void {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Demo',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'bruno-spa',
    ]);

    $response->assertStatus(201)
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

it('success body does not leak password or remember_token', function (): void {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Demo',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(201);

    $body = $response->json();
    expect($body)->not->toHaveKey('password');
    expect($body)->not->toHaveKey('password_confirmation');
    expect($body['user'])->not->toHaveKey('password');
    expect($body['user']['data'])->not->toHaveKey('password');
    expect($body['user']['data'])->not->toHaveKey('remember_token');
});

it('persists email_verified_at as now() at register', function (): void {
    $this->postJson('/api/auth/register', [
        'name' => 'Jane Demo',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(201);

    $user = User::query()->where('email', 'jane@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();
});

it('does not dispatch any Mailable at register', function (): void {
    $this->postJson('/api/auth/register', [
        'name' => 'Jane Demo',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(201);

    Mail::assertNothingSent();
});

it('hashes the password before persisting', function (): void {
    $this->postJson('/api/auth/register', [
        'name' => 'Jane Demo',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(201);

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();
    expect($user->password)->not->toBe('password');
    expect(Hash::check('password', $user->password))->toBeTrue();
});

it('register token authenticates /api/user immediately via Bearer', function (): void {
    $registerResponse = $this->postJson('/api/auth/register', [
        'name' => 'Jane Demo',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'angular-dev',
    ])->assertStatus(201);

    $token = $registerResponse->json('token');

    $userResponse = $this->withToken($token)->getJson('/api/user');

    $userResponse->assertOk()
        ->assertJsonPath('data.email', 'jane@example.com');
});

it('register response does not emit a session cookie', function (): void {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Demo',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(201);

    $hasSessionCookie = collect($response->headers->getCookies())
        ->contains(fn ($cookie) => in_array($cookie->getName(), ['laravel_session', 'XSRF-TOKEN'], true));

    expect($hasSessionCookie)->toBeFalse();
});

it('rejects register when password is shorter than 8 characters', function (): void {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Demo',
        'email' => 'short@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('enforces throttle on /api/auth/register', function (): void {
    RateLimiter::clear('register:127.0.0.1');
    RateLimiter::clear('register:::1');

    $payload = [
        'name' => 'Jane Demo',
        'email' => 'throttle@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/auth/register', $payload);
    }

    $blocked = $this->postJson('/api/auth/register', $payload);
    expect($blocked->getStatusCode())->toBe(429);
});
