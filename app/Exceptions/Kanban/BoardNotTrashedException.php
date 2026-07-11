<?php

declare(strict_types=1);

namespace App\Exceptions\Kanban;

use App\Models\KanbanBoard;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a restore is attempted on a board that is not currently
 * soft-deleted (i.e. its `deleted_at` IS NULL). Renders as HTTP 422
 * Unprocessable Entity with a typed `code` field the frontend can switch on.
 *
 * Distinct from `BoardHasContentsException` (which is 409 Conflict) because
 * the failure mode is "wrong lifecycle state for this action" rather than
 * "the entity is in a state that conflicts with the request". 422 is the
 * canonical choice for semantic validation per sdd/kanban/design §7.
 */
final class BoardNotTrashedException extends HttpException
{
    public function __construct(public readonly KanbanBoard $board)
    {
        parent::__construct(
            statusCode: 422,
            message: "Board {$board->id} is not soft-deleted and cannot be restored.",
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
            'code' => 'not_trashed',
            'board_id' => $this->board->id,
        ], 422);
    }
}
