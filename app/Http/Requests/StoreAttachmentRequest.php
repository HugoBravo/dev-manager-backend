<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload an attachment. The mime allowlist and size cap are LOCKED:
 *   - mime: jpg / jpeg / png / gif / webp / pdf / md / txt / zip
 *   - size: 5 MB (5120 KB)
 *
 * On mime rejection the `errors.file.0` code is the literal string
 * `attachment_mime_blocked` so the frontend can switch on it without
 * parsing free-text. Other validation failures (size, required) use
 * Laravel's default `errors.file` array with human-readable strings.
 *
 * The validation runs BEFORE any disk write — the controller MUST NOT
 * touch `Storage` until `$this->validated()` returns.
 */
final class StoreAttachmentRequest extends FormRequest
{
    /**
     * Locked mime allowlist. Server-side detection (Laravel's `mimes`
     * rule) inspects the actual file bytes, not the client-supplied
     * extension. Anything outside this list is rejected with
     * `attachment_mime_blocked`.
     */
    private const ALLOWED_MIMES = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf',
        'md', 'txt',
        'zip',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string|int>>
     */
    public function rules(): array
    {
        return [
            // `file` — present, valid upload, mime is on the allowlist,
            // size at most 5 MB (size rule interprets KB).
            'file' => ['required', 'file', 'mimes:'.implode(',', self::ALLOWED_MIMES), 'max:5120'],
        ];
    }

    /**
     * Translate Laravel's free-text mime-rejection message into the
     * locked typed code `attachment_mime_blocked`. Keeps the frontend's
     * switch on `errors.file[0]` uniform across mime and mimetype
     * mismatches.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $errors = $v->errors()->get('file');
            if ($errors === []) {
                return;
            }

            foreach ($errors as $error) {
                if (is_string($error) && (
                    str_contains($error, 'must be a file of type') ||
                    str_contains($error, 'extension')
                )) {
                    $v->errors()->forget('file');
                    $v->errors()->add('file', 'attachment_mime_blocked');

                    return;
                }
            }
        });
    }
}
