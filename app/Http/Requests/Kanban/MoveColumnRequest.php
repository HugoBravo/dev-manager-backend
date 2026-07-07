<?php

declare(strict_types=1);

namespace App\Http\Requests\Kanban;

use Illuminate\Foundation\Http\FormRequest;

final class MoveColumnRequest extends FormRequest
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
            'to_board_id' => ['required', 'integer', 'exists:kanban_boards,id'],
        ];
    }

    public function targetBoardId(): int
    {
        return (int) $this->validated('to_board_id');
    }
}
