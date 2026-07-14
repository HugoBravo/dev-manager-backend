<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class UserPolicy
{
    /**
     * Listing all users is reserved for admins (REASON: user enumeration is
     * privacy-sensitive; only admins should be able to iterate the user base).
     */
    public function viewAny(User $actor): bool
    {
        return $actor->is_admin === true;
    }

    /**
     * A user can always view their OWN record; admins can view any other user.
     */
    public function view(User $actor, User $target): bool
    {
        return $actor->is_admin === true || $actor->id === $target->id;
    }

    /**
     * Creating users is reserved for admins (new sign-ups via /auth/register
     * remain the public path).
     */
    public function create(User $actor): bool
    {
        return $actor->is_admin === true;
    }

    /**
     * Updating the OWN record is allowed; admins can update any user. Per-field
     * restrictions (e.g. only admins may change `email` or `is_admin`) are
     * enforced at the FormRequest layer, NOT here — this gate is binary.
     */
    public function update(User $actor, User $target): bool
    {
        return $actor->is_admin === true || $actor->id === $target->id;
    }

    /**
     * Only admins can delete users. The controller additionally refuses
     * self-deletion to avoid locking the system out.
     */
    public function delete(User $actor, User $target): bool
    {
        return $actor->is_admin === true;
    }
}
