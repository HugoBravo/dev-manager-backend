<?php

declare(strict_types=1);

namespace App\Http\Requests\Kanban;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Create a new kanban label for the authenticated user.
 *
 * - `name`  1-64 chars, unique per user (the (user_id, name) index in the
 *   migration enforces it; the validation rule surfaces a 422 instead of a
 *   SQL error).
 * - `color` 7-char `#RRGGBB` hex string. We accept lowercase and uppercase
 *   hex digits; the value is stored verbatim (the front-end is responsible
 *   for the canonical case).
 */
final class StoreKanbanLabelRequest extends FormRequest
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

        return [
            'name' => [
                'required',
                'string',
                'min:1',
                'max:64',
                Rule::unique('kanban_labels', 'name')->where('user_id', $userId),
            ],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
