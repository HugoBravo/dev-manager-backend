<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * R1 RESOLUTION (Batch 7) — single source of truth for the
 * `?include_archived=1` request-scoped convention.
 *
 * Background: the kanban capability soft-archives `projects` via the
 * `archived_at` timestamp (added in deviation #1 between Batch 2 and
 * Batch 3). Once a project is archived the user expects its nested
 * resources to vanish from default listing/show endpoints.
 *
 * Why a trait instead of a request class or middleware:
 *   - The decision point is per-controller and per-action (only `index`
 *     and `show` honour it; `store`/`update`/`destroy` accept the flag
 *     only for read consistency — a request to mutate a child of an
 *     archived project still succeeds because the user already has the
 *     URL, but it does not appear in lists). A middleware can't
 *     express "filter vs hide" cleanly.
 *   - The helper depends on the authenticated Request AND the
 *     resolved Project model, so a request class can't encapsulate it
 *     without breaking the type signature.
 *   - All five controllers (Board/Column/Card/Comment/Attachment)
 *     share the same helper, so a trait deduplicates the convention.
 *
 * Trade-off (documented in the Batch 7 brief):
 *   - This helper does NOT cover `Route::bind('card' | 'comment' |
 *     'attachment', ...)` for `show/update/destroy`. The chokepoint
 *     binding closures remain ownership-only; the archived-project
 *     filter runs at the controller entry, after binding succeeds.
 *     This keeps the bindings single-purpose (ownership scoping) and
 *     aligns with the existing controller-led pattern (the
 *     `resolveOwnedProject()` helper). Option (a) — extending closures
 *     to also check `archived_at` — was rejected because it would
 *     conflate two distinct concerns (ownership vs lifecycle state)
 *     inside one closure.
 */
trait KanbanRequestScope
{
    /**
     * Returns true if the caller asked to see archived-project resources.
     *
     * Reading order:
     *   1. The `?include_archived=1` query string on the request, if
     *      explicitly set (any truthy value).
     *   2. The `kanban.include_archived_default` config flag.
     *
     * Truthy values for the query string: "1", "true", "yes", "on"
     * (Laravel's `boolean()` is permissive). The config flag is a
     * strict bool.
     */
    protected function includeArchived(Request $request): bool
    {
        if ($request->has('include_archived')) {
            return $request->boolean('include_archived');
        }

        return (bool) config('kanban.include_archived_default', false);
    }

    /**
     * Throw `ModelNotFoundException` (404) when the resolved project is
     * archived AND the request did not opt in to viewing archived
     * resources. Centralized so every controller applies the same
     * gate; the alternative (inline `if` in five places) drifts.
     *
     * The exception bubbles through Laravel's exception handler and
     * renders as a 404 JSON envelope — matching the cross-owner
     * contract for existence-leak avoidance.
     */
    protected function ensureNotArchivedProject(
        Request $request,
        mixed $projectModel,
        string $projectClass,
        int|string $projectId,
    ): void {
        if ($this->includeArchived($request)) {
            return;
        }

        $archivedAt = $projectModel?->archived_at ?? null;
        if ($archivedAt !== null) {
            throw (new ModelNotFoundException)->setModel($projectClass, [$projectId]);
        }
    }
}
