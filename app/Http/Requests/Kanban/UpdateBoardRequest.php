<?php

declare(strict_types=1);

namespace App\Http\Requests\Kanban;

use App\Rules\UniqueActiveBoardName;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normalise the incoming name BEFORE the uniqueness check runs so
     * `Sprint 1` and `sprint  1  ` collide.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('name')) {
            return;
        }

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
                'sometimes',
                'required',
                'string',
                'min:1',
                'max:100',
                new UniqueActiveBoardName(
                    projectId: (int) $this->route('project')->getKey(),
                    ignoreBoardId: (int) $this->route('board')->getKey(),
                ),
            ],
        ];
    }
}
