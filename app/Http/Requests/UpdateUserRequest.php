<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if ($actor === null) {
            return false;
        }

        $target = $this->route('user');

        // Admins may update anyone; non-admins may update only themselves.
        // Per-field restrictions are enforced via `Rule::prohibitedIf`
        // per-field below so a privilege escalation surfaces as 422 (not
        // 403), giving the client a typed error key.
        return $actor->is_admin === true
            || ($target instanceof User && $actor->id === $target->id);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $actor = $this->user();

        $target = $this->route('user');
        $targetId = $target instanceof User ? (int) $target->getKey() : (int) $target;
        $isAdmin = $actor !== null && $actor->is_admin === true;

        return [
            'name' => ['sometimes', 'required', 'string', 'min:1', 'max:120'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($targetId),
                Rule::prohibitedIf(! $isAdmin),
            ],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'max:255'],
            'is_admin' => [
                'sometimes',
                'boolean',
                Rule::prohibitedIf(! $isAdmin),
            ],
        ];
    }
}
