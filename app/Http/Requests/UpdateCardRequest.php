<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update an existing card. All fields are sometimes — title, body, due_date.
 * Markdown body stored raw; empty string accepted (`body: ''`) and null
 * accepted (`body: null`) per the Batch 4 brief.
 */
final class UpdateCardRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'min:1', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }
}
