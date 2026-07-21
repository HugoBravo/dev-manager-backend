<?php

declare(strict_types=1);

namespace App\Http\Requests\Kanban;

use App\Rules\UniqueActiveBoardName;
use Illuminate\Foundation\Http\FormRequest;

final class StoreBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced inside the controller via the typed
        // Route::bind('board', ...) closure AND KanbanBoardPolicy::create. The
        // FormRequest authorize only verifies a bearer is present.
        return $this->user() !== null;
    }

    /**
     * Normalise the input BEFORE the validation rules run. The case-insensitive
     * uniqueness check requires a canonical form (trim + collapse internal
     * whitespace) so "Sprint 1" and "sprint  1  " match as the same name.
     */
    protected function prepareForValidation(): void
    {
        $name = (string) $this->input('name', '');
        $name = preg_replace('/\s+/', ' ', trim($name)) ?? '';

        $this->merge(['name' => $name]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:1',
                'max:100',
                new UniqueActiveBoardName(
                    taskId: (int) $this->route('task')->getKey(),
                ),
            ],
        ];
    }
}
