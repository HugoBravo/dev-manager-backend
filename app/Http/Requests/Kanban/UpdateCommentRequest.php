<?php

declare(strict_types=1);

namespace App\Http\Requests\Kanban;

use App\Models\KanbanComment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Update a comment. Body required (1-5000). The 15-minute edit window
 * is enforced via `withValidator()` returning a 422 when
 * `now()->diffInMinutes($comment->created_at) > config(...)`. Beyond
 * the window the comment is FROZEN — the policy says only the author
 * may edit; this rule adds the time-bound check.
 */
final class UpdateCommentRequest extends FormRequest
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            /** @var KanbanComment|null $comment */
            $comment = $this->route('comment');

            if (! $comment instanceof KanbanComment) {
                return;
            }

            $windowMinutes = (int) config('kanban.comment_edit_window_minutes');

            $minutesSinceCreation = $comment->created_at?->diffInMinutes(now()) ?? 0;

            if ($minutesSinceCreation > $windowMinutes) {
                $v->errors()->add(
                    'body',
                    "Comment edit window of {$windowMinutes} minute(s) has expired."
                );
            }
        });
    }
}
