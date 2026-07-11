<?php

declare(strict_types=1);

namespace App\Exceptions\Kanban;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a board create or rename attempts to use a name that already
 * exists on another ACTIVE board in the same project (case-insensitive).
 * Renders as HTTP 422 Unprocessable Entity with a typed `code` field the
 * frontend can switch on.
 *
 * Distinct from `BoardNotTrashedException` (which is also 422 but signals
 * wrong lifecycle state); `name_taken` is the canonical error code surfaced
 * by the BoardTest (Batch 1.5) acceptance tests and matches the project's
 * existing 422 / typed-code envelope convention per sdd/kanban/design §7.
 */
final class BoardAlreadyExistsException extends HttpException
{
    public function __construct(public readonly string $name, public readonly int $projectId)
    {
        parent::__construct(
            statusCode: 422,
            message: "A board named \"{$name}\" already exists in this project.",
        );
    }

    /**
     * Render the exception as a JSON response. Laravel's exception handler
     * invokes `render()` on HttpException subclasses automatically.
     */
    public function render(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'code' => 'name_taken',
            'name' => $this->name,
            'project_id' => $this->projectId,
        ], 422);
    }
}
