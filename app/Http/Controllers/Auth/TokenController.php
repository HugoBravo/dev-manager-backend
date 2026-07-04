<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TokenController extends Controller
{
    public function store(LoginRequest $request): JsonResponse
    {
        $user = $request->user() ?? auth()->user();

        if ($user === null) {
            $user = User::query()
                ->where('email', $request->string('email')->lower()->toString())
                ->first();
        }

        if ($user === null || ! password_verify((string) $request->input('password'), (string) $user->getAuthPassword())) {
            abort(422, 'invalid_credentials');
        }

        $deviceName = (string) $request->input('device_name', 'spa');

        $token = $user->createToken($deviceName);

        return response()->json([
            'token' => $token->plainTextToken,
            'abilities' => $token->accessToken->abilities,
        ]);
    }

    public function destroy(Request $request, int $tokenId): Response
    {
        $user = $request->user();

        $token = $user?->tokens()->where('id', $tokenId)->first();

        if ($token !== null) {
            $token->delete();
        }

        return response()->noContent();
    }
}
