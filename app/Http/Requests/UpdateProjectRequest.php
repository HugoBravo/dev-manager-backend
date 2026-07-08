<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProjectRequest extends FormRequest
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
        // `unique` must ignore the row currently being patched. The route
        // resolves `{project}` to a `Project` model via AppServiceProvider's
        // ownership-scoped Route::bind('project', ...) closure, so we pull
        // the primary key directly off the model — works for both id and slug
        // URI variants.
        $projectId = (int) $this->route('project')->getKey();

        return [
            'name' => ['sometimes', 'required', 'string', 'min:1', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'slug' => ['sometimes', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', "unique:projects,slug,{$projectId}"],
            // Date filter covers ISO 8601 strings; nullable to unarchive.
            'archived_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
