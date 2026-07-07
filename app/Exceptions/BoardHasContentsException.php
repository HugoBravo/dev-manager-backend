<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Board;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a DELETE is attempted on a board that still has columns under
 * it (cascading cards). Renders as HTTP 409 Conflict with a typed `code` field
 * the frontend can switch on, and the board id in the message so the caller
 * can surface a useful error to the user.
 *
 * Per sdd/kanban/design §7 the response follows Laravel's default JSON
 * envelope (`{"message", "code"}`) — we extend rather than replace it.
 */
final class BoardHasContentsException extends HttpException
{
    public function __construct(public readonly Board $board)
    {
        parent::__construct(
            statusCode: 409,
            message: "Board {$board->id} cannot be deleted while it contains columns or cards.",
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
            'code' => 'board_has_contents',
            'board_id' => $this->board->id,
        ], 409);
    }
}
