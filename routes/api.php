<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::post('/auth/token', [TokenController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('auth.token.store');

Route::delete('/auth/token/{tokenId}', [TokenController::class, 'destroy'])
    ->middleware('auth:sanctum')
    ->name('auth.token.destroy');
