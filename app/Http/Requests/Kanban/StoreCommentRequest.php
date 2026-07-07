<?php

declare(strict_types=1);

namespace App\Http\Requests\Kanban;

use App\Models\KanbanComment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create a comment. Body required (canonical text, 1-5000 chars, NOT
 * Markdown). `parent_id` optional — when provided the parent MUST exist
 * on the same card AND be authored by the same user. Cross-card /
 * cross-author parent_id is rejected at the validation layer, before
 * the authorization layer can leak existence.
 *
 * Edit window: this FormRequest is for `store`, so the time check does
 * not apply (a fresh comment is in-window by definition).
 */
final class StoreCommentRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'parent_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /**
     * Cross-layer invariants enforced via the withValidator hook so
     * they run AFTER the standard rules but BEFORE the controller.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $parentId = $this->input('parent_id');

            if ($parentId === null || $parentId === '') {
                return;
            }

            $parent = KanbanComment::query()->find($parentId);

            if ($parent === null) {
                $v->errors()->add('parent_id', 'Parent comment does not exist.');

                return;
            }

            // Cross-card parent rejected.
            if ($parent->card_id !== (int) $this->route('card')->id) {
                $v->errors()->add('parent_id', 'Parent comment belongs to a different card.');

                return;
            }

            // Cross-author parent rejected (thread-per-author invariant).
            if ($parent->author_id !== $this->user()->id) {
                $v->errors()->add(
                    'parent_id',
                    'Parent comment was authored by a different user; thread-per-author requires same author.'
                );
            }
        });
    }
}
