<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Bearer-mode contract: session cookies are NOT issued or consumed on /api/*.
// All authenticated routes resolve the user via auth:sanctum + Authorization: Bearer header.

Route::get('/user', function (Request $request) {
    $user = $request->user('sanctum') ?? $request->user();

    if ($user === null) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    return response()->json([
        'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ],
    ]);
})->middleware('auth:sanctum');

Route::post('/auth/register', [RegisterController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('auth.register');

Route::post('/auth/login', [SessionController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('auth.login');

Route::post('/auth/logout', [SessionController::class, 'destroy'])
    ->middleware('auth:sanctum')
    ->name('auth.logout');

// REMOVED in this migration: POST /auth/token (K1 — redundant with /auth/login).
// Per-device PAT revocation: DELETE /auth/tokens/{tokenId}.
Route::delete('/auth/tokens/{tokenId}', [SessionController::class, 'revokeToken'])
    ->middleware('auth:sanctum')
    ->name('auth.tokens.destroy');
