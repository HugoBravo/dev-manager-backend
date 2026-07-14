<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

final class UserController extends Controller
{
    /**
     * List all users (admin-only). Paginated, 25/page, ordered by id.
     * The UserPolicy::viewAny gate is the primary authorisation; cross-user
     * listing requires is_admin=true.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->orderBy('id')
            ->paginate(25);

        return UserResource::collection($users)->response();
    }

    /**
     * Show a single user. Admins may show anyone; non-admins may show only
     * themselves (enforced by UserPolicy::view).
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return (new UserResource($user))->response();
    }

    /**
     * Create a new user (admin-only). StoreUserRequest::authorize enforces
     * the admin gate; password is hashed automatically by the User model cast.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::query()->create($request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    /**
     * Update a user. Admins may update anyone; non-admins may update only
     * themselves. Per-field restrictions on `email`/`is_admin` for non-
     * admins are enforced by UpdateUserRequest via `Rule::prohibitedIf`.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $payload = $request->validated();

        $user->fill($payload)->save();

        return (new UserResource($user->fresh()))->response();
    }

    /**
     * Soft-delete a user (admin-only) and revoke every Sanctum token the
     * user holds. Admins cannot delete themselves to avoid locking the
     * system out (Risk R3 in the design).
     */
    public function destroy(Request $request, User $user): Response
    {
        $this->authorize('delete', $user);

        $actor = $request->user();

        if ($actor !== null && $actor->id === $user->id && $user->is_admin === true) {
            throw ValidationException::withMessages([
                'user' => ['An admin cannot delete their own account.'],
            ]);
        }

        // Revoke tokens BEFORE the model deletion so the relationship is
        // still queryable. Sanctum's HasApiTokens exposes tokens() as a
        // HasMany on personal_access_tokens.
        $user->tokens()->delete();

        $user->delete();

        return response()->noContent();
    }
}
