# Kanban API — Reference for Angular

Complete reference for the `kanban` capability of `dev-manager-backend`. Target
audience: Angular developers consuming the REST API from
`dev-manager-desk`. Every endpoint, request shape, response shape, and error
contract is documented here.

The doc reflects the post-chore-rename state (`feature/kanban-rename-kanban-namespace`).
URL paths, JSON shapes, and HTTP semantics are stable; SQL table names now use
the `kanban_*` prefix.

---

## Table of contents

1. [Overview](#1-overview)
2. [Conventions](#2-conventions)
3. [Resource shapes (JSON)](#3-resource-shapes-json)
4. [Projects](#4-projects)
5. [Boards](#5-boards)
6. [Columns](#6-columns)
7. [Cards](#7-cards)
8. [Comments](#8-comments)
9. [Attachments](#9-attachments)
10. [Status codes & error envelope](#10-status-codes--error-envelope)
11. [Cascade behavior](#11-cascade-behavior)
12. [Position ordering (fractional indexing)](#12-position-ordering-fractional-indexing)
13. [Markdown body contract](#13-markdown-body-contract)
14. [Thread-per-author comments](#14-thread-per-author-comments)
15. [Out-of-scope (do not expect)](#15-out-of-scope-do-not-expect)
16. [SQL table reference](#16-sql-table-reference)

---

## 1. Overview

The kanban capability lets a user create **projects**, and inside each project
manage **boards → columns → cards** with **comments** and **attachments**.

Everything is scoped to a **project**. There is no "personal" or "global" board
that escapes project scoping. A user who can access a project can read every
board inside it; a user who cannot access the project sees **404** for every
nested URL inside it (never 403 — see [Conventions](#2-conventions)).

### Resource tree

```
Project (global table, owned by user_id)
└── Board (FK: project_id)
    └── KanbanColumn (FK: board_id)
        └── KanbanCard (FK: column_id)
            ├── KanbanComment (FK: card_id, self-FK parent_id)
            └── KanbanAttachment (FK: card_id, file on local disk)
```

### Base URL

All kanban endpoints live under `/api/v1`. The version segment is mandatory.

```
https://<host>/api/v1/projects/{project}/kanban/...
```

### Authentication

Every endpoint requires a **Sanctum personal access token** sent as a Bearer
header. Unauthenticated requests return **401**.

```http
Authorization: Bearer {token}
Accept: application/json
```

Angular should send the token on every API request (typically via an `HttpInterceptor`).

### Required header for error JSON

Always send `Accept: application/json`. Without it Laravel may return HTML or
redirect to a login page on errors.

---

## 2. Conventions

These apply to **every** kanban endpoint.

### 2.1 Ownership and the 404-not-403 rule

Every nested URL (`/projects/{project}/kanban/...`) is implicitly bound to the
resource's owning project. Cross-owner access returns:

- **404 Not Found** for board / column / card / comment / attachment access
- **422 Unprocessable Entity** for *parent-validation* errors that would leak
  existence (e.g. moving a column to a board you don't own → 422, not 404)

The system **never** returns 403 for ownership mismatch. This prevents attackers
from probing whether a resource ID exists in someone else's account.

The single documented exception is **editing/deleting comments**: an
authenticated user editing a comment they did not author gets **403** (within the
edit window) or **403** (outside the window). See [Comments](#8-comments).

### 2.2 Pagination

List endpoints are paginated with Laravel's default `page` parameter and a
default page size of **25**. Response shape:

```json
{
  "data": [ ... ],
  "links": {
    "first": "https://.../api/v1/projects/.../cards?page=1",
    "last":  "https://.../api/v1/projects/.../cards?page=4",
    "prev":  null,
    "next":  "https://.../api/v1/projects/.../cards?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 4,
    "path": "https://.../api/v1/projects/.../cards",
    "per_page": 25,
    "to": 25,
    "total": 87
  }
}
```

To page: `?page=2`, `?page=3`, ...

### 2.3 Filter flags

| Flag | Default | Effect |
|---|---|---|
| `?archived=1` | hidden | Cards: include archived cards in list (only relevant for cards index) |
| `?include_archived=1` | hidden | Project hierarchy: include boards/columns/cards/comments/attachments whose parent project is `archived_at != null` |
| `?parent_id={comment_id}` | none | Comments index: filter by parent comment id |

These flags are **opt-in**. Default behavior is to hide archived / parent-filtered
data.

### 2.4 Response envelope

Every successful response (single resource) returns the bare resource shape:

```json
{
  "id": 17,
  "...": "..."
}
```

Every list returns the [pagination envelope](#22-pagination) above.

### 2.5 Rate limiting

Every kanban route uses the `throttle:api` middleware (Laravel default: 60
requests per minute per authenticated user). On exceed: **429 Too Many Requests**.

### 2.6 Timestamps

All timestamps are ISO 8601 in UTC. Format:

```
2026-07-07T15:42:18.000000Z
```

`due_date` (on Card) is a `YYYY-MM-DD` date string, not a timestamp.

---

## 3. Resource shapes (JSON)

These are the canonical response bodies. Fields marked `nullable` may be `null`.

### 3.1 Project

```json
{
  "id": 1,
  "name": "Demo Kanban Project",
  "slug": "demo-kanban-project",
  "owner_id": 1,
  "archived_at": null,
  "created_at": "2026-07-07T15:42:18.000000Z",
  "updated_at": "2026-07-07T15:42:18.000000Z"
}
```

`archived_at` is `null` while the project is active. When archived, the
project and **every nested resource** is hidden from default requests; pass
`?include_archived=1` to surface them.

### 3.2 Board

```json
{
  "id": 4,
  "project_id": 1,
  "name": "Sprint 42",
  "position": "n",
  "archived_at": null,
  "created_at": "2026-07-07T15:42:18.000000Z",
  "updated_at": "2026-07-07T15:42:18.000000Z"
}
```

`position` is a fractional-indexing string; see [Position ordering](#12-position-ordering-fractional-indexing).

### 3.3 Column

```json
{
  "id": 12,
  "board_id": 4,
  "name": "In Progress",
  "position": "u",
  "archived_at": null,
  "created_at": "2026-07-07T15:42:18.000000Z",
  "updated_at": "2026-07-07T15:42:18.000000Z"
}
```

### 3.4 Card

```json
{
  "id": 87,
  "column_id": 12,
  "title": "Implement login form",
  "body": "## Acceptance criteria\n\n- Form submits via [LoginService]",
  "due_date": "2026-07-15",
  "archived_at": null,
  "position": "k",
  "created_at": "2026-07-07T15:42:18.000000Z",
  "updated_at": "2026-07-07T15:42:18.000000Z"
}
```

`body` is **raw Markdown**, NOT HTML. See [Markdown body contract](#13-markdown-body-contract).
`due_date` is `YYYY-MM-DD` or `null`.

### 3.5 Comment

```json
{
  "id": 311,
  "card_id": 87,
  "parent_id": null,
  "author_id": 1,
  "body": "Looks good. One nit — typo on line 3.",
  "created_at": "2026-07-07T15:42:18.000000Z",
  "updated_at": "2026-07-07T15:42:18.000000Z"
}
```

See [Thread-per-author comments](#14-thread-per-author-comments) for `parent_id` semantics.

### 3.6 Attachment

```json
{
  "id": 41,
  "card_id": 87,
  "uploader_id": 1,
  "disk": "local",
  "path": "kanban/cards/87/4f8e3b21-a1d2-4e88-b8b3-sample.png",
  "original_filename": "sample.png",
  "mime": "image/png",
  "size_bytes": 12345,
  "url": null,
  "created_at": "2026-07-07T15:42:18.000000Z",
  "updated_at": "2026-07-07T15:42:18.000000Z"
}
```

`url` is currently `null` — the download endpoint is **out of scope** in v1.
The frontend can construct a download URL by reading `path` once the download
endpoint lands in a future change. Until then, **attachments are write-only**.

---

## 4. Projects

The Project is the global root. Every kanban resource hangs off a project.

### 4.1 List projects

```
GET /api/v1/projects
```

Returns the authenticated user's projects. By default, archived projects are
**excluded**. Pass `?include_archived=1` to include them.

**Query parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `include_archived` | bool | `0` | When `1`, archived projects are included in the response |

**Response**: paginated list of [Project](#31-project) resources.

```bash
curl -X GET \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  https://<host>/api/v1/projects
```

### 4.2 Create project

```
POST /api/v1/projects
```

**Body**

| Field | Type | Required | Constraints |
|---|---|---|---|
| `name` | string | yes | 1–255 chars |
| `slug` | string | no | `^[a-z0-9-]+$`, unique per owner; if omitted, auto-generated from `name` via `Str::slug` with `-2`, `-3`, … collision suffix |

**Response**: 201 Created with the new [Project](#31-project).

```bash
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"My new project"}' \
  https://<host>/api/v1/projects
```

### 4.3 Show project

```
GET /api/v1/projects/{project}
```

Returns 404 if project does not exist OR is archived and `include_archived=0`.

```bash
curl -X GET \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  https://<host>/api/v1/projects/1
```

### 4.4 Update project

```
PATCH /api/v1/projects/{project}
```

**Body** — all fields optional; only provided fields are updated.

| Field | Type | Constraints |
|---|---|---|
| `name` | string | 1–255 chars |
| `slug` | string | `^[a-z0-9-]+$`, unique per owner |
| `archived_at` | date or `null` | ISO 8601 timestamp; pass `null` to unarchive |

```bash
curl -X PATCH \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"archived_at": null}' \
  https://<host>/api/v1/projects/1
```

### 4.5 Delete project

```
DELETE /api/v1/projects/{project}
```

Hard-deletes the project. **All** boards, columns, cards, comments, and
attachments under it are cascade-deleted via FK constraints; attachment files
on disk are removed by the cascade trait. See
[Cascade behavior](#11-cascade-behavior).

**Response**: 204 No Content.

---

## 5. Boards

A Board belongs to a Project. One project can have many boards.

### 5.1 List boards of a project

```
GET /api/v1/projects/{project}/kanban/boards
```

**Query parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `include_archived` | bool | `0` | Include boards whose parent project is archived |

**Response**: paginated list of [Board](#32-board) resources, ordered by
`position` ASC.

```bash
curl -X GET \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  https://<host>/api/v1/projects/1/kanban/boards
```

### 5.2 Create board

```
POST /api/v1/projects/{project}/kanban/boards
```

**Body**

| Field | Type | Required | Constraints |
|---|---|---|---|
| `name` | string | yes | 1–255 chars |

`position` is auto-assigned at the end of the current position chain.

**Response**: 201 Created with the new [Board](#32-board).

### 5.3 Show board

```
GET /api/v1/projects/{project}/kanban/boards/{board}
```

### 5.4 Update board

```
PATCH /api/v1/projects/{project}/kanban/boards/{board}
```

**Body** — `name` optional.

### 5.5 Reorder boards

```
POST /api/v1/projects/{project}/kanban/boards/reorder
```

**Body**

```json
{
  "board_ids": [4, 7, 2, 9]
}
```

The order in the array becomes the new ordering; positions are recomputed
using fractional indexing. **All IDs must belong to the same project**;
otherwise 422.

### 5.6 Archive board

```
POST /api/v1/projects/{project}/kanban/boards/{board}/archive
```

Sets `archived_at = now()`. The board is hidden from default list requests.
**Response**: 200 OK with the updated [Board](#32-board).

### 5.7 Restore board

```
POST /api/v1/projects/{project}/kanban/boards/{board}/restore
```

Clears `archived_at`. **Response**: 200 OK with the updated [Board](#32-board).

### 5.8 Delete board

```
DELETE /api/v1/projects/{project}/kanban/boards/{board}
```

**Hard delete.** If the board has any columns (with or without cards), returns
**409 Conflict** with a typed `board_has_contents` error code; nothing is
deleted. Empty boards are deleted normally with **204 No Content**.

```json
// 409 Conflict
{
  "message": "Board has columns; cannot delete.",
  "code": "board_has_contents"
}
```

### 5.9 Boards — error matrix

| Condition | Status | Code |
|---|---|---|
| Cross-owner access | 404 | (default Laravel not-found body) |
| Project archived and `include_archived=0` | 404 | (default Laravel not-found body) |
| Board has columns on DELETE | 409 | `board_has_contents` |
| Validation failure | 422 | field-specific messages |
| Missing/invalid token | 401 | (default Laravel unauthenticated body) |
| Rate-limit exceeded | 429 | (Laravel throttle body) |

---

## 6. Columns

A Column belongs to a Board.

### 6.1 List columns of a board

```
GET /api/v1/projects/{project}/kanban/boards/{board}/columns
```

Ordered by `position` ASC.

### 6.2 Create column

```
POST /api/v1/projects/{project}/kanban/boards/{board}/columns
```

**Body**

| Field | Type | Required | Constraints |
|---|---|---|---|
| `name` | string | yes | 1–255 chars |

`position` is auto-assigned at the end of the current chain.

### 6.3 Show column

```
GET /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}
```

### 6.4 Update column

```
PATCH /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}
```

Body: `name` (optional, 1–255).

### 6.5 Reorder columns within board

```
POST /api/v1/projects/{project}/kanban/boards/{board}/columns/reorder
```

**Body**

```json
{
  "column_ids": [12, 15, 11]
}
```

### 6.6 Move column to another board (cross-board move)

```
POST /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/move
```

**Body**

| Field | Type | Required | Constraints |
|---|---|---|---|
| `target_board_id` | int | yes | Must belong to the same project |
| `position` | string | no | Fractional index; if omitted, appended to target board's chain |

The column is moved **with all its cards** preserved. If the target board is
in another project (or belongs to another user), the request fails with
**404** (existence-leak prevention).

### 6.7 Archive column

```
POST /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/archive
```

### 6.8 Restore column

```
POST /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/restore
```

### 6.9 Delete column

```
DELETE /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}
```

Hard delete. **409 Conflict** with `column_has_contents` if the column has any
cards; otherwise 204.

---

## 7. Cards

A Card belongs to a Column. Cards carry the actual work units.

### 7.1 List cards of a column

```
GET /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards
```

**Query parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `archived` | bool | `0` | When `1`, archived cards are included |
| `include_archived` | bool | `0` | When `1`, cards whose parent project is archived are included |

Ordered by `position` ASC.

### 7.2 Show card

```
GET /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards/{card}
```

### 7.3 Create card

```
POST /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards
```

**Body**

| Field | Type | Required | Constraints |
|---|---|---|---|
| `title` | string | yes | 1–255 chars |
| `body` | string | no | 0–65,535 chars; raw Markdown |
| `due_date` | date | no | `YYYY-MM-DD` |

### 7.4 Update card

```
PATCH /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards/{card}
```

All fields optional. Empty-string semantics are preserved: sending `body: ""`
clears the body; sending `body: null` also clears it (both result in `null` in
the database).

### 7.5 Archive card

```
POST /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards/{card}/archive
```

### 7.6 Restore card

```
POST /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards/{card}/restore
```

### 7.7 Reorder cards within column

```
POST /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards/reorder
```

**Body**

```json
{
  "card_ids": [87, 89, 88]
}
```

### 7.8 Move card to another column (cross-column move)

```
POST /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards/{card}/move
```

**Body**

| Field | Type | Required | Constraints |
|---|---|---|---|
| `target_column_id` | int | yes | Must belong to the same project |
| `position` | string | no | Fractional index; if omitted, appended to target column |

Cross-project target → **404**. Same project, but target column ID unknown
→ **404** (existence-leak prevention). See
[Position ordering](#12-position-ordering-fractional-indexing).

### 7.9 Delete card

```
DELETE /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards/{card}
```

**Hard delete.** Cascades:

- DB rows in `kanban_comments` for this card are deleted
- DB rows in `kanban_attachments` for this card are deleted
- Files on disk for those attachments are removed (transactional with row
  deletion; partial failure rolls back)

Response: **204 No Content**.

---

## 8. Comments

Comments are children of Cards. They use a **thread-per-author** model.

### 8.1 List comments of a card

```
GET /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards/{card}/comments
```

**Query parameters**

| Name | Type | Description |
|---|---|---|
| `parent_id` | int | Filter by parent comment id (use for thread fan-out) |

Pagination: page size 25.

The list is **flat**. The frontend is responsible for grouping by `parent_id`
to render a thread. See [Thread-per-author](#14-thread-per-author-comments).

### 8.2 Show comment

```
GET /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards/{card}/comments/{comment}
```

### 8.3 Create comment

```
POST /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards/{card}/comments
```

**Body**

| Field | Type | Required | Constraints |
|---|---|---|---|
| `body` | string | yes | 1–5,000 chars; canonical text (NOT Markdown semantics) |
| `parent_id` | int | no | Must reference an existing comment on **the same card** AND authored by the **same user**. Cross-card or cross-author `parent_id` → **422 Unprocessable Entity** |

```bash
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"body": "Looks good."}' \
  https://<host>/api/v1/projects/1/kanban/boards/4/columns/12/cards/87/comments
```

### 8.4 Update comment

```
PATCH /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards/{card}/comments/{comment}
```

**Body**: `body` required, 1–5,000 chars.

**Allowed only if both conditions hold:**

1. `comment.author_id === current_user.id`
2. `now() - comment.created_at <= config('kanban.comment_edit_window_minutes')` (default 15)

If either fails → **403 Forbidden** with the standard Laravel authorization
exception body. **403 is the documented exception** to the 404-not-403 rule.

### 8.5 Delete comment

```
DELETE /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards/{card}/comments/{comment}
```

Same author-and-window constraints as update. Outside the window or non-author
→ **403**. Within the window by the author → **204**.

### 8.6 Comments — error matrix

| Condition | Status |
|---|---|
| Comment belongs to a card in another project (cross-owner) | 404 |
| Edit/delete by non-author | 403 |
| Edit/delete outside the 15-min window | 403 |
| Cross-card or cross-author `parent_id` on create | 422 |
| `body` empty or > 5,000 chars | 422 |
| Project archived and `include_archived=0` | 404 |

---

## 9. Attachments

Attachments are files stored on the server's local disk. v1 is **upload-and-list**
only — there is no download endpoint yet.

### 9.1 List attachments of a card

```
GET /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards/{card}/attachments
```

Paginated, page size 25.

### 9.2 Upload attachment

```
POST /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards/{card}/attachments
```

Content-Type: `multipart/form-data`

**Body**

| Field | Type | Required | Constraints |
|---|---|---|---|
| `file` | file | yes | Allowed mimes: `jpg, jpeg, png, gif, webp, pdf, md, txt, zip`. Max size: **5 MB** (5,242,880 bytes). |

On mime rejection (e.g. `.exe`): **422 Unprocessable Entity** with code
`attachment_mime_blocked`. **No row is created and no file is written to disk.**

On size rejection: **422** with standard Laravel size error.

On success: **201 Created** with the new [Attachment](#36-attachment) resource.
The file is stored at `storage/app/private/kanban/cards/{card_id}/{uuid}.{ext}`.

```bash
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -F "file=@/path/to/photo.jpg" \
  https://<host>/api/v1/projects/1/kanban/boards/4/columns/12/cards/87/attachments
```

### 9.3 Delete attachment

```
DELETE /api/v1/projects/{project}/kanban/boards/{board}/columns/{column}/cards/{card}/attachments/{attachment}
```

Removes the row and the file from disk in a single transaction.

**Cross-owner delete → 404** (existence-leak prevention).

---

## 10. Status codes & error envelope

| Status | When |
|---|---|
| **200 OK** | Successful read or update |
| **201 Created** | Successful create (POST) |
| **204 No Content** | Successful delete or archive/restore mutation |
| **401 Unauthorized** | Missing or invalid Sanctum token |
| **403 Forbidden** | ONLY for: editing/deleting a comment outside the 15-minute edit window OR by a non-author |
| **404 Not Found** | Cross-owner access; project archived and `include_archived=0`; resource does not exist |
| **409 Conflict** | Delete of a non-empty board (`board_has_contents`); delete of a non-empty column (`column_has_contents`) |
| **422 Unprocessable Entity** | Validation failure (missing field, length, mime, etc.) — the typed `attachment_mime_blocked` error also returns 422 |
| **429 Too Many Requests** | Rate-limit exceeded (default 60 req/min/user) |

### 10.1 Default Laravel error JSON shape

For 404 / 401 / 403 (default):

```json
{
  "message": "..."
}
```

### 10.2 Validation 422

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."],
    "file": ["The file must be a file of type: jpg, jpeg, png, gif, webp, pdf, md, txt, zip."]
  }
}
```

### 10.3 Typed 409

```json
{
  "message": "Board has columns; cannot delete.",
  "code": "board_has_contents"
}
```

Same envelope for `column_has_contents`.

### 10.4 Typed 422 (attachment mime)

```json
{
  "message": "Attachment mime is not allowed.",
  "errors": {
    "file": ["The file must be a file of type: jpg, jpeg, png, gif, webp, pdf, md, txt, zip."]
  },
  "code": "attachment_mime_blocked"
}
```

Frontend should treat the typed `code` as a stable, documented contract and
not localize on the human-readable `message`.

---

## 11. Cascade behavior

| Action | Cascades to |
|---|---|
| DELETE project | All boards, columns, cards, comments, attachments, attachment files on disk |
| DELETE board (only if empty) | Nothing — but the request fails 409 if columns exist |
| DELETE column (only if empty) | Nothing — request fails 409 if cards exist |
| DELETE card | All comments on the card (DB rows) + all attachments on the card (DB rows + files on disk) |
| DELETE comment | Nothing — comments have no children |
| DELETE attachment | Nothing else — file and row only |

Cascade file deletion is performed in the controller layer (`CascadesKanbanCardFiles`
trait) inside a `DB::transaction`. **There is no model observer** that auto-deletes
files. Adding one would cause double-deletion or skip.

If a contributor ever modifies `CardController::destroy` or `AttachmentController::destroy`,
the cascade order is:

1. Snapshot all attachment paths inside the transaction
2. Delete the card (FK cascade removes `kanban_comments` and `kanban_attachments` rows)
3. `Storage::disk('local')->delete($paths)` for each path
4. Transaction commits; if step 3 throws, the row deletion rolls back

---

## 12. Position ordering (fractional indexing)

All boards, columns, and cards carry a `position` string column. The system uses
**string-fractional indexing**: the position is a string that sorts lexicographically,
and reorders compute a new value between two existing values.

### Rules for the Angular client

- **Never set `position` on create.** The backend computes it automatically.
- On reorder / move, the backend accepts a `position` parameter if you want to
  insert between two specific values; otherwise the new position is appended.
- The position is opaque. Don't try to parse it or assume length.
- The hard cap on string length is **1024 bytes**. In practice you will never
  see values longer than ~10 characters unless thousands of inserts happen
  between the same pair.

### Algorithm summary (for context only)

| Operation | Position chosen |
|---|---|
| Append at end | `next(latest_position)` |
| Prepend at start | `prev(first_position)` |
| Insert between `a` and `b` | midpoint in alphabet-based scheme |

If the algorithm exhausts the 1024-byte cap, the request fails with **422**
and an explicit `position_exhausted` error code. The backend includes a
tested rebalance path documented for a future change.

---

## 13. Markdown body contract

`card.body` is **raw Markdown**, stored verbatim. The backend does not parse,
render, or sanitize it.

- **Max length**: 65,535 characters (TEXT column)
- **Preserved as-is**: HTML tags (`<script>`, etc.) are stored verbatim — they are
  not stripped, escaped, or sanitized
- **Frontend responsibility**: render with a Markdown library (e.g. `marked`,
  `markdown-it`) configured with a sanitizer (e.g. DOMPurify) before injecting
  into the DOM. Never use `innerHTML` directly with `card.body`.
- **Optional**: pass `null` or `""` to clear

This is different from `comment.body`, which is **canonical text** (length-bounded
plain text without Markdown semantics — frontend renders it as preformatted text).

---

## 14. Thread-per-author comments

Comments do NOT support arbitrary nested replies (no Reddit/HN-style tree).
The model is:

- **Same author replies** to a comment → use `parent_id = existing_comment.id`
- **Different author replies** → create a new top-level comment (`parent_id = null`),
  even if it's a response to the previous comment

This keeps the data model flat and the frontend simple. The list endpoint
returns all comments for a card flat; the frontend groups them by author +
parent chain.

```json
// GET /comments on card 87
{
  "data": [
    { "id": 311, "parent_id": null, "author_id": 1, "body": "Initial thought." },
    { "id": 312, "parent_id": null, "author_id": 2, "body": "Different author, new thread." },
    { "id": 313, "parent_id": 311, "author_id": 1, "body": "Author 1 replying to themselves." },
    { "id": 314, "parent_id": null, "author_id": 3, "body": "Third voice, third thread." }
  ]
}
```

Validation:

- `parent_id` must reference a comment on **the same card**
- `parent_id` must reference a comment by **the same author**
- Either constraint failing → **422**

If the product later wants arbitrary nested replies, this is a schema migration
on `kanban_comments.parent_id` (likely removing the same-author constraint,
adding threading depth limit, etc.) — treat as a follow-up change.

---

## 15. Out-of-scope (do not expect)

The following are explicitly **not implemented** in this backend. Building
Angular features that assume they exist will fail.

| Feature | Status | Replacement change |
|---|---|---|
| Attachment download endpoint | Not implemented | Future change |
| Real-time updates (WebSockets / SSE / Pusher) | Not implemented | Future change |
| Project sharing (`project_user` pivot, roles) | Not implemented | Future change |
| Notifications (any channel) | Not implemented | Future change |
| Webhooks (incoming or outgoing) | Not implemented | Future change |
| Labels / tags on cards | Not implemented | Future change |
| Checklists on cards | Not implemented | Future change |
| Activity log / event stream | Not implemented | Future change |
| Full-text search across cards | Not implemented | Future change |
| Soft delete on projects/boards/columns | Not implemented | `archived_at` only, as a UI filter |
| Soft delete on cards | Not implemented | `archived_at` only, as a UI filter |
| Background jobs / Redis | Not implemented | Synchronous only |
| `attachment.url` field | Always `null` | Until download endpoint ships |

If the frontend needs any of these, file a follow-up change request — they
were intentionally deferred to keep the kanban change scope contained.

---

## 16. SQL table reference

For engineers who want to inspect the database directly or write admin tooling.
**These names are internal** — the API contract is defined by the JSON shapes
above, not by these table names.

| Table | Purpose |
|---|---|
| `projects` | Global project root (shared with future modules) |
| `kanban_boards` | Boards inside a project |
| `kanban_columns` | Columns inside a board |
| `kanban_cards` | Cards inside a column (carries Markdown body) |
| `kanban_comments` | Comments inside a card (thread-per-author) |
| `kanban_attachments` | File metadata for attachments; files live on `local` disk |

### Foreign-key chain

```
projects.id           ← kanban_boards.project_id  (cascadeOnDelete)
kanban_boards.id      ← kanban_columns.board_id  (cascadeOnDelete)
kanban_columns.id     ← kanban_cards.column_id   (cascadeOnDelete)
kanban_cards.id       ← kanban_comments.card_id  (cascadeOnDelete)
kanban_cards.id       ← kanban_attachments.card_id (cascadeOnDelete)
kanban_comments.id    ← kanban_comments.parent_id (cascadeOnDelete; self-ref, same-author)
users.id              ← projects.owner_id         (cascadeOnDelete)
users.id              ← kanban_comments.author_id (nullOnDelete)
users.id              ← kanban_attachments.uploader_id (nullOnDelete)
```

If a user is hard-deleted, `kanban_comments.author_id` and
`kanban_attachments.uploader_id` are set to NULL (preserving the rows but
orphaning the authorship). `projects.owner_id` cascades to delete the
project entirely.

---

## Appendix A — quick curl smoke test

A minimum end-to-end smoke test, assuming you have a valid Sanctum token:

```bash
TOKEN=your_token_here
HOST=https://<host>

# 1. Create project
PROJECT_ID=$(curl -s -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Smoke test"}' \
  $HOST/api/v1/projects | jq -r .id)

# 2. Create board
BOARD_ID=$(curl -s -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"Sprint 1\"}" \
  $HOST/api/v1/projects/$PROJECT_ID/kanban/boards | jq -r .id)

# 3. Create column
COLUMN_ID=$(curl -s -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Backlog"}' \
  $HOST/api/v1/projects/$PROJECT_ID/kanban/boards/$BOARD_ID/columns | jq -r .id)

# 4. Create card
CARD_ID=$(curl -s -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"title":"My first card","body":"## Hello"}' \
  $HOST/api/v1/projects/$PROJECT_ID/kanban/boards/$BOARD_ID/columns/$COLUMN_ID/cards | jq -r .id)

# 5. Add comment
curl -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"body":"Looks good!"}' \
  $HOST/api/v1/projects/$PROJECT_ID/kanban/boards/$BOARD_ID/columns/$COLUMN_ID/cards/$CARD_ID/comments

# 6. Upload attachment
curl -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -F "file=@/path/to/photo.jpg" \
  $HOST/api/v1/projects/$PROJECT_ID/kanban/boards/$BOARD_ID/columns/$COLUMN_ID/cards/$CARD_ID/attachments
```

## Appendix B — pagination helper for Angular

```typescript
interface Paginated<T> {
  data: T[];
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
  meta: {
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
    path: string;
  };
}

async function fetchAll<T>(firstPageUrl: string): Promise<T[]> {
  const all: T[] = [];
  let url: string | null = firstPageUrl;
  while (url) {
    const page: Paginated<T> = await fetch(url, { headers }).then(r => r.json());
    all.push(...page.data);
    url = page.links.next;
  }
  return all;
}
```

## Appendix C — error-handling helper for Angular

```typescript
type ApiError =
  | { kind: 'unauthenticated'; status: 401; message: string }
  | { kind: 'not_found'; status: 404; message: string } // includes ownership-leak 404
  | { kind: 'forbidden'; status: 403; message: string } // ONLY for comment edit window
  | { kind: 'conflict'; status: 409; code: 'board_has_contents' | 'column_has_contents'; message: string }
  | { kind: 'mime_blocked'; status: 422; code: 'attachment_mime_blocked'; message: string; errors: Record<string, string[]> }
  | { kind: 'validation'; status: 422; message: string; errors: Record<string, string[]> }
  | { kind: 'rate_limited'; status: 429; message: string };

async function callApi<T>(input: RequestInfo, init?: RequestInit): Promise<T> {
  const res = await fetch(input, {
    ...init,
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json', ...init?.headers },
  });
  if (res.ok) return res.status === 204 ? (undefined as T) : res.json();
  const body = await res.json().catch(() => ({ message: res.statusText }));
  throw normalizeError(res.status, body);
}

function normalizeError(status: number, body: any): ApiError {
  switch (status) {
    case 401: return { kind: 'unauthenticated', status, message: body.message };
    case 404: return { kind: 'not_found', status, message: body.message };
    case 403: return { kind: 'forbidden', status, message: body.message };
    case 409: return { kind: 'conflict', status, code: body.code, message: body.message };
    case 422:
      if (body.code === 'attachment_mime_blocked') return { kind: 'mime_blocked', status, code: body.code, message: body.message, errors: body.errors };
      return { kind: 'validation', status, message: body.message, errors: body.errors };
    case 429: return { kind: 'rate_limited', status, message: body.message };
    default:  throw new Error(`Unhandled status ${status}: ${JSON.stringify(body)}`);
  }
}
```

---

*This doc reflects the post-chore-rename state on
`feature/kanban-rename-kanban-namespace`. When kanban changes land, update this
doc in the same PR.*