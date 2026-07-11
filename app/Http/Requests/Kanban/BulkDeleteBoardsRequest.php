<?php

declare(strict_types=1);

namespace App\Http\Requests\Kanban;

/**
 * Final-form request for `POST /boards/bulk-delete`. The base validation
 * (ids array 1..N where N = config('kanban.bulk_max_ids')) is provided by
 * `prepareForValidation` + `rules()`.
 */
final class BulkDeleteBoardsRequest extends BulkBoardsRequest
{
    // Body keys (`ids`) are provided by the base class.
}
