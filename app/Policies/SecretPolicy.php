<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Secret;
use App\Models\User;

/**
 * Secret authorization — delegates to ProjectPolicy (the ownership chokepoint).
 * Every method routes through `$user->can('view', $secret->project)`.
 *
 * Cross-owner resource leak avoidance (404-not-403) is handled at route binding
 * time by the `Route::bind('secret', ...)` closure in AppServiceProvider::boot().
 */
final class SecretPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Secret $secret): bool
    {
        return $user->can('view', $secret->project);
    }

    public function update(User $user, Secret $secret): bool
    {
        return $user->can('view', $secret->project);
    }

    public function delete(User $user, Secret $secret): bool
    {
        return $user->can('view', $secret->project);
    }
}
