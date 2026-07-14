<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Secret;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Project-scoped, case-insensitive uniqueness check for `secrets.key`.
 *
 * Why a custom rule instead of Laravel's `Rule::unique`:
 *   - `Rule::unique` cannot compare against `LOWER(key)` (functional column
 *     expression) — the underlying SQL index for the secrets module is
 *     case-sensitive (`(project_id, key)`), so a plain `Rule::unique`
 *     would let `DB_PASSWORD` and `db_password` coexist.
 *   - The rule normalises the candidate to lowercase before the SQL
 *     query, matching the case-insensitive contract that the test
 *     `rejects duplicate keys within the same project (case-insensitive)`
 *     exercises.
 *   - On INSERT race (two simultaneous posts both pass validation but
 *     collide at the DB), the controller catches
 *     `UniqueConstraintViolationException` and re-renders the 422 — see
 *     SecretController::store.
 */
final class UniqueSecretKey implements ValidationRule
{
    /**
     * @param  int  $projectId  The owning project id from the request route.
     * @param  int|null  $ignoreSecretId  The secret id to ignore (used on update).
     */
    public function __construct(
        private readonly int $projectId,
        private readonly ?int $ignoreSecretId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $query = Secret::query()
            ->where('project_id', $this->projectId)
            ->whereRaw('LOWER(key) = ?', [mb_strtolower($value)]);

        if ($this->ignoreSecretId !== null) {
            $query->where('id', '!=', $this->ignoreSecretId);
        }

        if ($query->exists()) {
            $fail('A secret with that key already exists in this project.');
        }
    }
}
