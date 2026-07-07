<?php

declare(strict_types=1);

namespace App\Http\Requests\Kanban;

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
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:100'],
        ];
    }
}
