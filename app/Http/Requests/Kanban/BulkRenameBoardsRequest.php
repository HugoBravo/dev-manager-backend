<?php

declare(strict_types=1);

namespace App\Http\Requests\Kanban;

use Illuminate\Validation\Rule;

final class BulkRenameBoardsRequest extends BulkBoardsRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'prefix' => ['required', 'string', 'min:1', 'max:50'],
            'mode' => ['required', 'string', Rule::in(['add', 'remove'])],
        ]);
    }
}
