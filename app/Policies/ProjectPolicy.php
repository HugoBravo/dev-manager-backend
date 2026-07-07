<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Ownership chokepoint for the kanban capability.
 * Every kanban child policy delegates back to one of these methods via
 * `$user->can('view', $parent->project)`.
 */
final class ProjectPolicy
{
    /**
     * Any authenticated user may create a project they will own.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id;
    }

    public function update(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id;
    }
}
