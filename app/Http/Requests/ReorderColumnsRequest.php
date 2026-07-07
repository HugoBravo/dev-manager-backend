<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\KanbanColumn;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class ReorderColumnsRequest extends FormRequest
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
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'distinct', 'exists:kanban_columns,id'],
        ];
    }

    /**
     * Cross-check that EVERY id belongs to the route-bound board.
     * `$this->route('board')` returns the Board model — Laravel 13's
     * SubstituteBindings substitutes wildcard models into route params
     * BEFORE form-request validation runs.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $board = $this->route('board');
            $boardId = is_object($board) ? (int) $board->id : (int) $board;

            $ids = (array) $this->input('ordered_ids', []);

            if ($ids === []) {
                return;
            }

            $count = KanbanColumn::query()
                ->whereIn('id', $ids)
                ->where('board_id', $boardId)
                ->count();

            if ($count !== count($ids)) {
                $v->errors()->add('ordered_ids', 'Some column ids do not belong to this board.');
            }
        });
    }

    /**
     * @return array<int, int>
     */
    public function orderedIds(): array
    {
        return array_values(array_map('intval', (array) $this->validated('ordered_ids')));
    }
}
