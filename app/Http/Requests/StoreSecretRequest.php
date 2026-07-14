<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Project;
use App\Rules\UniqueSecretKey;
use Illuminate\Foundation\Http\FormRequest;

final class StoreSecretRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normalise the key by trimming surrounding whitespace BEFORE the
     * regex + unique-within-project rules run. We DO NOT collapse internal
     * whitespace — keys with embedded spaces are rejected by the regex
     * (`^[A-Za-z0-9._-]+$`) instead, matching the "key is a name, not a
     * phrase" convention.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'key' => trim((string) $this->input('key', '')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'key' => [
                'required',
                'string',
                'min:1',
                'max:100',
                'regex:/^[A-Za-z0-9._@+-]+$/',
                new UniqueSecretKey(
                    projectId: $project instanceof Project ? (int) $project->getKey() : (int) $project,
                ),
            ],
            'value' => ['required', 'string', 'min:1', 'max:8192'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
