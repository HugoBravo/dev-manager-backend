<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSecretRequest extends FormRequest
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
        return [
            'value' => ['sometimes', 'required', 'string', 'min:1', 'max:8192'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
