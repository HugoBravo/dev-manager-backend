<?php

declare(strict_types=1);

namespace App\Http\Requests\Kanban;

use App\Models\KanbanCard;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reorder cards within a column. `$this->route('column')` returns the
 * KanbanColumn model (Laravel 13 SubstituteBindings substitutes wildcard
 * models into route params before form-request validation) — must handle the
 * `is_object()` polymorph (Batch 3 finding, ditto ReorderColumnsRequest).
 */
final class ReorderCardsRequest extends FormRequest
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
            'ordered_ids.*' => ['integer', 'distinct', 'exists:kanban_cards,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $column = $this->route('column');
            $columnId = is_object($column) ? (int) $column->id : (int) $column;

            $ids = (array) $this->input('ordered_ids', []);

            if ($ids === []) {
                return;
            }

            $count = KanbanCard::query()
                ->whereIn('id', $ids)
                ->where('column_id', $columnId)
                ->count();

            if ($count !== count($ids)) {
                $v->errors()->add('ordered_ids', 'Some card ids do not belong to this column.');
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
