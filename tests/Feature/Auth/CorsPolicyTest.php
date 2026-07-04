<?php

declare(strict_types=1);

it('reads allowed origins from SANCTUM_ALLOWED_ORIGINS with SANCTUM_STATEFUL_DOMAINS fallback', function (): void {
    expect(config('cors.allowed_origins'))->toContain('http://localhost')
        ->toContain('http://localhost:4200');
});

it('does not advertise Access-Control-Allow-Credentials in bearer mode', function (): void {
    $response = $this->options('/api/user', [], [
        'Origin' => 'http://localhost:4200',
        'Access-Control-Request-Method' => 'GET',
        'Access-Control-Request-Headers' => 'authorization',
    ]);

    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('http://localhost:4200');
    expect($response->headers->get('Access-Control-Allow-Credentials'))->toBeNull();
});

it('echoes disallowed origins negatively', function (): void {
    $response = $this->options('/api/user', [], [
        'Origin' => 'http://evil.example.com',
        'Access-Control-Request-Method' => 'GET',
    ]);

    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('http://evil.example.com');
});

it('echoes Authorization header in preflight Access-Control-Allow-Headers', function (): void {
    $response = $this->options('/api/user', [], [
        'Origin' => 'http://localhost:4200',
        'Access-Control-Request-Method' => 'GET',
        'Access-Control-Request-Headers' => 'authorization',
    ]);

    expect(strtolower((string) $response->headers->get('Access-Control-Allow-Headers')))
        ->toContain('authorization');
});
