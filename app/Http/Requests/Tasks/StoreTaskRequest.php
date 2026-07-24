<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $project = $this->route('project');
        $projectId = $project instanceof Project ? $project->getKey() : $project;

        return [
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'slug' => [
                'sometimes',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('tasks', 'slug')->where(fn ($query) => $query->where('project_id', $projectId)),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(['open', 'in_progress', 'done'])],
            'priority' => ['sometimes', 'string', Rule::in(['HIGH', 'MEDIUM', 'LOW'])],
        ];
    }
}
