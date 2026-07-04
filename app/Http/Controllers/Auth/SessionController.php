<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class SessionController extends Controller
{
    public function store(LoginRequest $request): UserResource
    {
        $credentials = $request->only(['email', 'password']);

        if (! Auth::guard('web')->attempt($credentials, remember: false)) {
            abort(422, 'invalid_credentials');
        }

        $request->session()->regenerate();

        return new UserResource(Auth::guard('web')->user());
    }

    public function destroy(): Response
    {
        Auth::guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return response()->noContent();
    }
}
