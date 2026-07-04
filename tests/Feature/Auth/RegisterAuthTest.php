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
    $response = $this->withHeaders(['Origin' => 'http://localhost:4200'])
        ->postJson('/api/auth/register', [
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('rejects register with invalid email format', function (): void {
    $response = $this->withHeaders(['Origin' => 'http://localhost:4200'])
        ->postJson('/api/auth/register', [
            'name' => 'Jane Demo',
            'email' => 'not-an-email',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('rejects register when password is not confirmed', function (): void {
    $response = $this->withHeaders(['Origin' => 'http://localhost:4200'])
        ->postJson('/api/auth/register', [
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

    $response = $this->withHeaders(['Origin' => 'http://localhost:4200'])
        ->postJson('/api/auth/register', [
            'name' => 'Jane Demo',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('registers a new user, returns 201 + envelope + session cookie', function (): void {
    $response = $this->withHeaders(['Origin' => 'http://localhost:4200'])
        ->postJson('/api/auth/register', [
            'name' => 'Jane Demo',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'bruno-spa',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'email_verified_at']]);

    $cookies = $response->headers->getCookies();
    $hasSessionCookie = collect($cookies)->contains(fn ($cookie) => in_array($cookie->getName(), ['laravel_session', 'XSRF-TOKEN'], true));
    expect($hasSessionCookie)->toBeTrue();
});

it('success body does not leak password or remember_token', function (): void {
    $response = $this->withHeaders(['Origin' => 'http://localhost:4200'])
        ->postJson('/api/auth/register', [
            'name' => 'Jane Demo',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $response->assertStatus(201);

    $body = $response->json();
    expect($body)->not->toHaveKey('password');
    expect($body['data'])->not->toHaveKey('password');
    expect($body['data'])->not->toHaveKey('remember_token');
});

it('persists email_verified_at as now() at register', function (): void {
    $this->withHeaders(['Origin' => 'http://localhost:4200'])
        ->postJson('/api/auth/register', [
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
    $this->withHeaders(['Origin' => 'http://localhost:4200'])
        ->postJson('/api/auth/register', [
            'name' => 'Jane Demo',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(201);

    Mail::assertNothingSent();
});

it('hashes the password before persisting', function (): void {
    $this->withHeaders(['Origin' => 'http://localhost:4200'])
        ->postJson('/api/auth/register', [
            'name' => 'Jane Demo',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(201);

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();
    expect($user->password)->not->toBe('password');
    expect(Hash::check('password', $user->password))->toBeTrue();
});

it('sets session cookie when Origin matches SANCTUM_STATEFUL_DOMAINS', function (): void {
    $response = $this->withHeaders(['Origin' => 'http://localhost:4200'])
        ->postJson('/api/auth/register', [
            'name' => 'Jane Demo',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $response->assertStatus(201);

    $cookies = $response->headers->getCookies();
    $hasSessionCookie = collect($cookies)->contains(fn ($cookie) => in_array($cookie->getName(), ['laravel_session', 'XSRF-TOKEN'], true));
    expect($hasSessionCookie)->toBeTrue();
});

it('lets the registered user authenticate GET /api/user with the session cookie', function (): void {
    $this->withHeaders(['Origin' => 'http://localhost:4200'])
        ->postJson('/api/auth/register', [
            'name' => 'Jane Demo',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(201);

    $userResponse = $this->withHeaders(['Origin' => 'http://localhost:4200'])
        ->getJson('/api/user');

    $userResponse->assertOk()
        ->assertJsonPath('data.email', 'jane@example.com');
});
