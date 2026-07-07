<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Move a card cross-column. The target column id is validated as `exists:
 * kanban_columns,id` here; the controller then performs the owner-scoped
 * lookup so cross-project or cross-owner target columns return 404 (not 422)
 * — mirror of `MoveColumnRequest` in Batch 3 R3.
 */
final class MoveCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'to_column_id' => ['required', 'integer', 'exists:kanban_columns,id'],
        ];
    }

    public function targetColumnId(): int
    {
        return (int) $this->validated('to_column_id');
    }
}
