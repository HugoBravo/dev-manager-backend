<?php

it('echoes origin and sets Allow-Credentials for stateful origins', function (): void {
    $response = $this->options('/api/user', [], [
        'Origin' => 'http://localhost:4200',
        'Access-Control-Request-Method' => 'GET',
        'Access-Control-Request-Headers' => 'X-XSRF-TOKEN',
    ]);

    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('http://localhost:4200');
    expect($response->headers->get('Access-Control-Allow-Credentials'))->toBe('true');
});

it('does not echo disallowed origins', function (): void {
    $response = $this->options('/api/user', [], [
        'Origin' => 'http://evil.example.com',
        'Access-Control-Request-Method' => 'GET',
    ]);

    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('http://evil.example.com');
});
