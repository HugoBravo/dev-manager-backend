<?php

declare(strict_types=1);

namespace App\Http\Requests\Kanban;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base validation rules shared by the bulk board endpoints
 * (`bulk-delete`, `bulk-rename`). Enforces:
 *   - `ids`: required array, 1..N ints, all coerced via
 *     `prepareForValidation()` so input strings like "1,2,3" become arrays.
 *   - `kanban.bulk_max_ids` is the authoritative cap (defaults to 100).
 *
 * Subclasses (BulkRenameBoardsRequest, BulkDeleteBoardsRequest) extend
 * `rules()` to add their own payload-specific keys. Concrete subclasses
 * are required because Laravel's container cannot instantiate an abstract
 * FormRequest.
 */
class BulkBoardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Coerce the incoming `ids` array to integers. Accepts both
     * `[1, 2, 3]` and `["1", "2", "3"]` shapes so the frontend can
     * send either format.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('ids')) {
            return;
        }

        $ids = $this->input('ids', []);
        if (! is_array($ids)) {
            return;
        }

        $this->merge(['ids' => array_values(array_map(static function ($value): int {
            return (int) $value;
        }, $ids))]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $maxIds = (int) config('kanban.bulk_max_ids', 100);

        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.$maxIds],
            'ids.*' => ['integer', 'min:1'],
        ];
    }
}
