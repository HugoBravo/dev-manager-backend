<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

final class TaskPolicy
{
    public function create(User $user, Project $project): bool
    {
        return app(ProjectPolicy::class)->create($user);
    }

    public function view(User $user, Task $task): bool
    {
        return app(ProjectPolicy::class)->view($user, $task->project);
    }

    public function update(User $user, Task $task): bool
    {
        return app(ProjectPolicy::class)->update($user, $task->project);
    }

    public function archive(User $user, Task $task): bool
    {
        return app(ProjectPolicy::class)->update($user, $task->project)
            && ! $task->hasActiveBoards();
    }
}
