<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\KanbanBoard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Project-scoped, case-insensitive, soft-delete-aware uniqueness check for
 * `kanban_boards.name`. The functional expression `LOWER(name)` makes the
 * comparison case-insensitive in SQL (works on both pgsql and sqlite), and
 * `WHERE deleted_at IS NULL` lets a recycled name succeed when the previous
 * holder is soft-deleted (the restore window is the recovery path for
 * accidental renames).
 *
 * Why a custom rule instead of Laravel's `Rule::unique`:
 *   - `Rule::unique` does NOT accept a functional column expression (`LOWER`).
 *   - `Rule::unique` cannot express `whereNull('deleted_at')` AND a normalised
 *     `name = LOWER(?)` projection at the same time.
 *   - The case-insensitive comparison must happen server-side because the
 *     underlying SQL indexes `LOWER(name)` (see the migration in
 *     `2026_07_11_010002_add_unique_index_board_name_active`).
 *
 * @see \Database\Migrations\2026_07_11_010002_add_unique_index_board_name_active
 */
final class UniqueActiveBoardName implements ValidationRule
{
    /**
     * @param  int  $projectId  The owning project id from the request route.
     * @param  int|null  $ignoreBoardId  The board id to ignore (used on update).
     */
    public function __construct(
        private readonly int $projectId,
        private readonly ?int $ignoreBoardId = null,
    ) {}

    /**
     * Run the SQL check: any row in (project_id, LOWER(name)) — excluding
     * $ignoreBoardId AND any soft-deleted row — fails the rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $query = KanbanBoard::query()
            ->where('project_id', $this->projectId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
            ->whereNull('deleted_at');

        if ($this->ignoreBoardId !== null) {
            $query->where('id', '!=', $this->ignoreBoardId);
        }

        if ($query->exists()) {
            $fail('A board with that name already exists in this project.');
        }
    }
}
