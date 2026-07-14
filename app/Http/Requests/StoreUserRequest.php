<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->is_admin === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'is_admin' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'A user with this email already exists.',
        ];
    }
}
