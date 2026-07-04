<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists session across sequential requests with XSRF cookie', function (): void {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('password'),
    ]);

    $csrfResponse = $this->get('/sanctum/csrf-cookie');
    $csrfResponse->assertNoContent();
    $xsrf = null;
    foreach ($csrfResponse->headers->getCookies() as $cookie) {
        if ($cookie->getName() === 'XSRF-TOKEN') {
            $xsrf = $cookie->getValue();
            break;
        }
    }
    expect($xsrf)->not->toBeNull();

    $login = $this->withHeaders([
        'Origin' => 'http://localhost:4200',
        'X-XSRF-TOKEN' => $xsrf,
    ])->postJson('/api/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'password',
        'device_name' => 'spa',
    ]);

    $login->assertOk();

    $userResponse = $this->getJson('/api/user');
    $userResponse->assertOk()
        ->assertJsonPath('data.email', 'jane@example.com');
});

it('issues bearer token usable against /api/user', function (): void {
    $user = User::factory()->create();
    $plain = $user->createToken('cli')->plainTextToken;

    $this->withToken($plain)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});
