<?php

declare(strict_types=1);

use App\Models\User;

/*
|--------------------------------------------------------------------------
| SanctumAuthTest (Batch 5 — bearer end-to-end)
|--------------------------------------------------------------------------
|
| Locked item from Batch 1's risk register: prove the bearer path actually
| reaches the v1 routes. Earlier batches use `actingAs($user, 'sanctum')`
| (guard-bound) without traversing the bearer-token middleware. This file
| exercises the real `auth:sanctum` middleware with a minted personal access
| token.
|
*/

it('reaches a v1 protected endpoint using a Sanctum personal access token', function (): void {
    $user = User::factory()->create();

    // Owner with no projects — index returns 200 with empty data envelope.
    $token = bearerFor($user);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v1/projects');

    $response->assertOk()->assertJsonStructure([
        'data' => [],
    ]);
});

it('rejects an invalid bearer token with 401', function (): void {
    $this->withHeaders(['Authorization' => 'Bearer invalid-token-string'])
        ->getJson('/api/v1/projects')
        ->assertUnauthorized();
});
