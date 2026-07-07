<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create a new card. Title required (max 255); body optional raw Markdown
 * (max 65,535 chars); due_date optional Y-m-d date. Markdown is stored raw
 * with no HTML sanitization — the front-end is responsible for safe rendering.
 */
final class StoreCardRequest extends FormRequest
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
            'title' => ['required', 'string', 'min:1', 'max:255'],
            'body' => ['nullable', 'string', 'max:65535'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
