# Backend Refactor: Kanban per Task — Frontend Impact

> Frontend-facing summary of the backend data-model refactor.
> Use this when planning the Angular 22 (`dev-manager-desk`) client changes.
> Backend project: `dev-manager-backend` (Laravel 13 / PHP 8.3, Sanctum bearer auth, Pest + strict TDD).

## TL;DR

Today the backend associates Kanban boards directly with projects:

```
Project ──< KanbanBoard ──< KanbanColumn ──< KanbanCard
```

After the refactor, a `Task` entity is inserted between project and board so each task owns its own kanban:

```
Project ──< Task ──< KanbanBoard ──< KanbanColumn ──< KanbanCard
```

URL chains grow by one segment: existing `/projects/{project}/kanban/...` becomes `/projects/{project}/tasks/{task}/kanban/...`. The frontend must follow.

---

## 1. New domain model

### Entities

| Entity | Table | New / changed | Key fields | Notes |
|---|---|---|---|---|
| `Task` | `tasks` | NEW | `project_id`, `name`, `slug`, `description`, `status`, `archived_at` | Greenfield layer — no existing `tasks` table. First-class domain entity. |
| `Project` | `projects` | shape unchanged | `owner_id`, `name`, `description`, `slug`, `archived_at` | Adds `tasks()` HasMany. |
| `KanbanBoard` | `kanban_boards` | FK change | `task_id` *(replaces `project_id`)*, `name`, `position`, `archived_at`, `deleted_at` | Cascade on task delete via FK + explicit cleanup. |
| `KanbanColumn` | `kanban_columns` | unchanged | `board_id`, `name`, `position`, `archived_at` | — |
| `KanbanCard` | `kanban_cards` | unchanged | `column_id`, `title`, `body`, `position`, `archived_at` | — |
| `KanbanComment` | `kanban_comments` | unchanged | `card_id`, `author_id`, `body`, `edited_at` | 15-min edit window. |
| `KanbanAttachment` | `kanban_attachments` | unchanged | `card_id`, `disk_path`, `mime`, `size`, `original_name` | Controller-led cleanup, cascade-on-card-delete. |
| `KanbanLabel` | `kanban_labels` | unchanged | `user_id`, `name`, `color` | Global per-user (NOT project-scoped, NOT task-scoped). |
| `KanbanBoardAuditLog` | `kanban_board_audit_logs` | unchanged | `board_id`, `actor_id`, `action`, `payload` | Append-only. |

### Cascade ownership

```
Project (soft archive: `archived_at`)
  └── Task      (soft archive: `archived_at`)
        └── KanbanBoard  (soft delete: `deleted_at`)
              ├── KanbanColumn
              │     └── KanbanCard
              ├── KanbanComment     (cascade on card delete)
              └── KanbanAttachment  (controller-led cleanup)
```

`KanbanLabel` stays user-global and continues to attach to cards. It does NOT move to the task layer.

---

## 2. URL contract changes

### Before vs after (representative routes)

| Operation | Before | After |
|---|---|---|
| List boards | `GET /api/v1/projects/{project}/kanban/boards` | `GET /api/v1/projects/{project}/tasks/{task}/kanban/boards` |
| Show board | `GET /api/v1/projects/{project}/kanban/boards/{board}` | `GET /api/v1/projects/{project}/tasks/{task}/kanban/boards/{board}` |
| Create board | `POST /api/v1/projects/{project}/kanban/boards` | `POST /api/v1/projects/{project}/tasks/{task}/kanban/boards` |
| Reorder boards | `POST …/boards/reorder` | `POST …/tasks/{task}/kanban/boards/reorder` |
| Bulk delete | `POST …/boards/bulk-delete` | `POST …/tasks/{task}/kanban/boards/bulk-delete` |
| Clone board | `POST …/boards/{board}/clone` | `POST …/tasks/{task}/kanban/boards/{board}/clone` |
| Audit log | `GET …/boards/{board}/audit` | `GET …/tasks/{task}/kanban/boards/{board}/audit` |
| Trashed list | `GET …/boards/trashed` | `GET …/tasks/{task}/kanban/boards/trashed` |
| Restore | `POST …/boards/{boardId}/restore` | `POST …/tasks/{task}/kanban/boards/{boardId}/restore` |

Cards, columns, comments and attachments keep their **relative path under the board**, but the absolute prefix shifts one level. Labels remain global at `/api/v1/kanban-labels` and do NOT move under the project/task chain.

### New task routes (to add)

| Operation | Route |
|---|---|
| List tasks | `GET /api/v1/projects/{project}/tasks` |
| Show task | `GET /api/v1/projects/{project}/tasks/{task}` |
| Create task | `POST /api/v1/projects/{project}/tasks` |
| Update task | `PATCH /api/v1/projects/{project}/tasks/{task}` |
| Archive task | `POST /api/v1/projects/{project}/tasks/{task}/archive` |
| Restore task | `POST /api/v1/projects/{project}/tasks/{task}/restore` |

### Migration strategy for clients

This is a breaking URL change. Two implementation options, pending product decision:

- **Clean cut** (recommended): drop the old `/projects/{p}/kanban/...` shape. Frontend aligns in one PR-equivalent (work-unit batch in solo-commits mode).
- **Transitional alias**: backend maps the legacy URL to a default task per project during a deprecation window. Adds complexity.

The refactor will decide in `sdd-design`.

---

## 3. Request / response shape changes

### Resources

- **`TaskResource` (NEW)**: `{ id, project_id, name, slug, description, status, archived_at, created_at, updated_at }`.
- **`BoardResource`** (updated):
  - `project_id` is replaced by `task_id`.
  - Adds an embedded `task` relationship: `{ id, name, slug, status, archived_at }`.
- **`ColumnResource`, `CardResource`, `CommentResource`, `AttachmentResource`, `KanbanLabelResource`, `BoardAuditLogResource`**, **`BulkOperationResult`**: unchanged.

### Pagination / sorting

- Task list: standard Laravel `?per_page=` / `?page=`.
- Kanban resource list endpoints: pagination key unchanged.
- Board list supports the existing `?include_archived=1` flag (now scoped under task).

### Error semantics

The backend uses `Route::bind` closures that scope ownership at every level. Frontend must keep treating these consistently:

| HTTP | Meaning |
|---|---|
| `404` | Resource missing OR resource exists but does not belong to the parent in the URL chain (e.g., board is not under that task). |
| `403` | User lacks the project role. |
| `409` | Soft-delete conflict (e.g., board has cards but client asks to delete) OR `PositionExhaustedException`. Backend uses `Gate::inspect()` so the response is 409, not 403. |
| `422` | Validation error. |

Frontend should treat 404-on-mismatch as **"refresh parent chain, refetch"**, not as a permission denial.

---

## 4. Authorization impact

- **Chokepoint**: `ProjectPolicy` stays the chokepoint. A new `TaskPolicy` gates task-level operations (create / update / archive a task inside a project).
- Sanctum bearer auth unchanged.
- The 404-not-403 ownership leak guard still applies at every level (now including task). `AppServiceProvider::boot()` will grow a `Route::bind('task', ...)` closure.

---

## 5. Data migration

Existing dev / production rows have `kanban_boards.project_id`. The refactor:

1. Migration `…_create_tasks_table.php` — new table.
2. Migration `…_add_task_id_to_kanban_boards_table.php` — nullable add.
3. Data migration: for each existing project with boards, create one default `Task` per project (e.g., `name = "Default"`) and re-parent `kanban_boards.task_id`. If a project has multiple active boards, decide policy (one task per board group vs one task per project) in `sdd-propose`.
4. Migration `…_drop_project_id_from_kanban_boards_table.php` — drop column once the data is re-parented.

### Frontend-visible state during the rollout

- During migration window: `kanban_boards.task_id` may be nullable.
- Post-migration: all responses have `task_id` populated.
- Cached boards from before the cut may resolve to the migration-time default task; cleared caches on next refetch.

---

## 6. Backend artifacts the change will introduce

When the change ships, expect the following in `dev-manager-backend`:

| Layer | New / changed |
|---|---|
| Migration | `…_create_tasks_table.php`, `…_add_task_id_to_kanban_boards_table.php`, `…_drop_project_id_from_kanban_boards_table.php` |
| Model | `app/Models/Task.php` *(NEW)*, `Project.php` *(add `tasks()` HasMany)*, `KanbanBoard.php` *(FK replaced)* |
| Factory | `database/factories/TaskFactory.php` *(NEW)* |
| Policy | `app/Policies/TaskPolicy.php` *(NEW)* |
| Controller | `app/Http/Controllers/Api/V1/Tasks/TaskController.php` *(NEW)*; kanban controllers adapted for the `{task}` chain |
| Service / concerns | `app/Services/Kanban/Concerns/ResolvesKanbanChain.php` *(extend with `ensureBoardBelongsToTask`)*, `KanbanRequestScope` *(extend traversal)* |
| Resource | `app/Http/Resources/TaskResource.php` *(NEW)*, `BoardResource.php` *(expose `task`)* |
| Routes | `routes/api/v1.php` — new `/projects/{project}/tasks/...`, kanban chain updated |
| Tests | `tests/Feature/Tasks/TaskTest.php` *(NEW)*, kanban tests updated for the new URL chain |

Backend test layers involved: `RefreshDatabase` (project-wide), `tests/Pest.php` helpers, factories. Test gaps known to exist for `ColumnController`, `BoardBulkOperationsController`, `CardLabelController`, `ResolvesKanbanChain`, `ComputesKanbanPositions`, `CascadesKanbanCardFiles`, `BoardAlreadyExistsException` — the refactor will extend the blast radius on these.

---

## 7. Open questions for the frontend

These surface BEFORE the frontend team commits to an implementation plan:

1. **URL migration policy** — clean cut or alias mapping for the URL chain?
2. **Task field set** — beyond `name` / `description` / `status` / `archived_at`, does the frontend want `assignee`, `due_date`, `priority` on Task? (Drives backend shape too.)
3. **One board per task, or many?** — backend is leaning one-active-board-per-task (matches the existing `unique_active_board_name` shape). Confirm with product.
4. **Archive cascade UX** — does archiving a Task in the UI confirm with the user first, since it hides all boards / cards / columns under it?
5. **Label scope** — labels remain card-level and global-per-user; no task-level labels. Confirm with product.
6. **Error UX** — keep the 404-on-mismatch refresh flow as-is for the new chain.
7. **Optimistic updates** — current kanban supports `?include_archived=1`. Confirm frontend wants to propagate that flag through the new task layer (likely yes).
8. **Demo seeder parity** — `DemoProjectSeeder` will likely gain a default task; ensure the dev frontend smoke test still loads boards.

---

## 8. Suggested work breakdown (Angular 22)

Tentative task slicing for `dev-manager-desk`. The final breakdown will be fixed in `sdd-tasks` after the backend plan locks.

1. **API client**: update OpenAPI types / service classes to match the new URL chain and the `task` resource. Mirror the new `TaskResource` shape.
2. **Routing**: align the Angular route tree to the new prefix (`/projects/:projectId/tasks/:taskId/kanban/...`).
3. **Task CRUD screens**: new components for list / create / edit / archive tasks within a project.
4. **Board / column / card components**: rebind to the new URL chain; swap `projectId` for `taskId` in inputs and reactive signals.
5. **State management**: adjust any cached "active board per project" to "active board per task"; update selectors / stores accordingly.
6. **Error handling**: confirm 404-on-mismatch refresh flow works with the new chain. Keep `409` semantics for soft-delete conflicts.
7. **E2E tests (Bruno + frontend)**: update existing bruno requests to new URLs; add coverage for task CRUD.
8. **i18n / labels**: add strings for task CRUD screens.

---

## 9. Reference: backend modules touched

```
app/Models/Project.php                                                 # add tasks() HasMany
app/Models/Task.php                                                    # NEW
app/Models/KanbanBoard.php                                             # task_id instead of project_id
app/Models/KanbanColumn.php                                            # unchanged
app/Models/KanbanCard.php                                              # unchanged
database/migrations/..._create_tasks_table.php                         # NEW
database/migrations/..._add_task_id_to_kanban_boards_table.php         # NEW
database/migrations/..._drop_project_id_from_kanban_boards_table.php    # NEW
app/Http/Controllers/Api/V1/Tasks/TaskController.php                   # NEW
app/Http/Controllers/Api/V1/Kanban/{Board,Column,Card,...}Controller.php  # resolve to task chain
app/Policies/TaskPolicy.php                                            # NEW
app/Services/Kanban/Concerns/ResolvesKanbanChain.php                   # extend
app/Services/Kanban/Concerns/KanbanRequestScope.php                     # extend traversal
app/ValueObjects/Kanban/Position.php                                   # unchanged (still shared)
app/Http/Resources/TaskResource.php                                    # NEW
app/Http/Resources/BoardResource.php                                   # expose task
routes/api/v1.php                                                      # add /tasks routes; update kanban chain
tests/Feature/Tasks/TaskTest.php                                       # NEW
tests/Feature/Kanban/{Board,Column,Card,...}Test.php                   # update URL chain
```

---

*Owner: `dev-manager-backend` SDD change "kanban-per-task". Generated as frontend handoff companion, 2026-07-20.*
*Cross-reference the SDD explore / proposal / spec artifacts (engram) for the canonical backend plan. Cross-reference the previous session summary (obs #113) and the `sdd-init/dev-manager-backend` context (obs #60) for project conventions and gotchas.*
