<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class SessionController extends Controller
{
    public function store(LoginRequest $request): JsonResponse
    {
        $email = (string) $request->input('email');
        $password = (string) $request->input('password');

        // Manual lookup so we can ALSO exclude soft-deleted users (scenario
        // S14 of the user-administration capability). Auth::guard('web')-
        // >attempt() goes through EloquentUserProvider which ignores the
        // SoftDeletes global scope.
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $deviceName = (string) $request->input('device_name', 'unknown');
        $token = $user->createToken($deviceName);

        return response()->json([
            'user' => (new UserResource($user))->resolve(),
            'token' => $token->plainTextToken,
        ]);
    }

    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function revokeToken(Request $request, int $tokenId): Response
    {
        $token = $request->user()?->tokens()->where('id', $tokenId)->first();

        if ($token !== null) {
            $token->delete();
        }

        return response()->noContent();
    }
}
