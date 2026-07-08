<?php

declare(strict_types=1);

namespace App\Http\Requests\Kanban;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Update an existing kanban label owned by the authenticated user.
 *
 * Both fields are `sometimes` so a PATCH can rename without recoloring
 * (or vice versa). The uniqueness rule ignores the current label id so
 * a no-op PATCH (same name) does not 422.
 */
final class UpdateKanbanLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string|Unique>>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;
        $labelId = $this->route('label');

        return [
            'name' => [
                'sometimes',
                'string',
                'min:1',
                'max:64',
                Rule::unique('kanban_labels', 'name')->where('user_id', $userId)->ignore($labelId),
            ],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
