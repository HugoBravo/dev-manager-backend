<?php

declare(strict_types=1);

namespace App\Http\Requests\Kanban;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sync the set of labels on a card.
 *
 * Body shape: `{ "label_ids": [1, 2, 3] }`. The PUT semantics REPLACE
 * the current set — empty array clears all labels. Each id must belong
 * to the authenticated user; cross-user ids → 422 (the controller
 * enforces ownership after validation).
 *
 * The `distinct` rule prevents duplicates so the same id cannot be
 * repeated. The `exists` rule is scoped to the user via the
 * `where` clause so a stale id that exists in the DB but belongs to
 * another user fails validation (no existence-leak of other users'
 * label ids).
 */
final class SyncCardLabelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'label_ids' => ['present', 'array'],
            'label_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('kanban_labels', 'id')->where('user_id', $userId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'label_ids.present' => 'The label_ids field is required (use [] to clear all labels).',
        ];
    }

    /**
     * @return list<int>
     */
    public function labelIds(): array
    {
        /** @var list<int> $ids */
        $ids = $this->validated('label_ids') ?? [];

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function withValidator(Validator $validator): void
    {
        // No-op: cross-user ids are already caught by the
        // `Rule::exists(...)->where('user_id', $userId)` rule above. The
        // hook is here in case a future change wants to add a custom
        // error code (e.g. `label_not_owned`) on the 422 envelope.
    }
}
