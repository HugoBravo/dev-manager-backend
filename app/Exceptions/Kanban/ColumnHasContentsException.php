<?php

declare(strict_types=1);

namespace App\Exceptions\Kanban;

use App\Models\KanbanColumn;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a DELETE is attempted on a column that still has cards under
 * it (cascading into the future `cards` table from Batch 4). Renders as
 * HTTP 409 Conflict with a typed `code` field the frontend can switch on.
 *
 * Distinct from `BoardHasContentsException` because the column-level 409
 * carries the column id (not the board id); consumers need to know which
 * entity refused the delete so they can present a targeted message.
 *
 * Per sdd/kanban/design §7 the response follows Laravel's default JSON
 * envelope (`{"message", "code", "column_id"}`) — we extend rather than
 * replace it.
 */
final class ColumnHasContentsException extends HttpException
{
    public function __construct(public readonly KanbanColumn $column)
    {
        parent::__construct(
            statusCode: 409,
            message: "Column {$column->id} cannot be deleted while it contains cards.",
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
            'code' => 'column_has_contents',
            'column_id' => $this->column->id,
        ], 409);
    }
}
