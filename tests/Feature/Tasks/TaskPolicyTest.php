<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Policies\TaskPolicy;

it('delegates task authorization decisions to the project policy', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->forOwner($owner)->create();
    $task = Task::factory()->create(['project_id' => $project->id]);
    $policy = app(TaskPolicy::class);

    expect($policy->view($owner, $task))->toBeTrue()
        ->and($policy->update($owner, $task))->toBeTrue()
        ->and($policy->archive($owner, $task))->toBeTrue()
        ->and($policy->view($stranger, $task))->toBeFalse()
        ->and($policy->update($stranger, $task))->toBeFalse();
});
