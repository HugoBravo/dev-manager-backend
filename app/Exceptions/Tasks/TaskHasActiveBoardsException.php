<?php

declare(strict_types=1);

namespace App\Exceptions\Tasks;

use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class TaskHasActiveBoardsException extends HttpException
{
    public function __construct(public readonly Task $task)
    {
        parent::__construct(
            statusCode: 409,
            message: "Task {$task->id} cannot be archived while it contains active boards.",
        );
    }

    public function render(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'code' => 'task_has_active_boards',
            'task_id' => $this->task->id,
        ], 409);
    }
}
