<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Board;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class ReorderBoardsRequest extends FormRequest
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
            'ordered_ids.*' => ['integer', 'distinct', 'exists:boards,id'],
        ];
    }

    /**
     * Cross-check that EVERY id belongs to the project named in the URL.
     * Validation must run AFTER the standard rule chain.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $projectId = $this->route('project');
            $ids = (array) $this->input('ordered_ids', []);

            if ($ids === []) {
                return;
            }

            $count = Board::query()
                ->whereIn('id', $ids)
                ->where('project_id', $projectId)
                ->count();

            if ($count !== count($ids)) {
                $v->errors()->add('ordered_ids', 'Some board ids do not belong to this project.');
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
