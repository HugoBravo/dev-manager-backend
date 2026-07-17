# PostgreSQL-to-MySQL Compatibility Audit

**Repository:** `dev-manager-backend`  
**Artifact language:** English  
**Audit date:** 2026-07-14  
**Scope:** Determine whether changing only `.env` from PostgreSQL to MySQL is sufficient for the Laravel 13 application to work correctly. No application code was changed during this audit.

## Executive verdict

**No. An environment-only switch is not sufficient.**

The Laravel connection configuration and most application queries are portable, but the migration history contains a **blocking MySQL incompatibility**: migration `2026_07_11_010002_add_unique_index_board_name_active.php` uses a MySQL fallback that still emits `LOWER(name)` as an index key expression. MySQL does not support PostgreSQL-style partial unique indexes, and this expression is not portable as written across supported MySQL versions. A fresh MySQL migration is therefore expected to fail at that migration, or the application will lose its intended soft-delete-aware uniqueness semantics if the migration is weakened.

There are also operational and validation gaps: the repository currently runs tests on SQLite, CI does not provision or test MySQL, deployment copies a separate environment file, and the currently inspected database has one pending migration. Existing PostgreSQL data cannot be made available by changing `.env` alone; it requires a MySQL schema/data migration or a logical export/import process.

### Decision table

| Area | Environment-only change | Finding |
|---|---:|---|
| Laravel connection selection | Yes, if PHP has `pdo_mysql` | Configuration already defines `mysql` |
| Fresh schema creation | **No** | Driver-specific unique-index migration is not MySQL-safe |
| Existing PostgreSQL data | **No** | Requires conversion/export/import and validation |
| Application CRUD/query code | Mostly yes | Eloquent/query-builder code is largely portable |
| Test confidence | **No** | Tests force SQLite and CI does not test MySQL |
| Deployment | **No** | Production deploy uses `.env.llamadev`, not the repository `.env` |
| Production cutover | **No** | Requires backup, migration, cache/config handling, and rollback plan |

## Confidence and assumptions

- **Confidence:** High (approximately 0.90) for the conclusion that `.env` alone is insufficient; medium-high (approximately 0.80) for every MySQL-version-specific outcome because the exact target MySQL version and server SQL mode were not provided.
- The target is assumed to be Oracle MySQL, not MariaDB. Laravel defines both drivers separately in `config/database.php`.
- The intended process is assumed to be either a fresh MySQL schema or a PostgreSQL-to-MySQL production migration. Those are different operations; neither is completed by changing connection variables alone.
- The audit did not inspect secret values. `.env` and `.env.llamadev` were treated as sensitive; only the presence of DB keys was checked.
- CodeGraph was used first for structural exploration. Filesystem inspection and read-only Artisan commands were then used for evidence and verification.

## Repository state and version evidence

The working tree was **not clean before the audit**:

- Branch: `main`, ahead of `origin/main` by one commit.
- Existing unrelated modifications: `.atl/.skill-registry.cache.json` and `.atl/skill-registry.md`.
- Latest commit at audit time: `515b0fd feat(users): CRUD module with admin gate and self-service profile`.
- No files other than the requested report were modified by this audit.

Installed/locked versions verified from Laravel Boost and `composer.lock`:

| Component | Version |
|---|---:|
| PHP | 8.3 |
| Laravel Framework | 13.18.1 |
| Sanctum | 4.3.2 |
| Laravel Boost | 2.4.11 |
| Laravel Pint | 1.29.3 |
| Pest | 4.7.4 |
| PHPUnit | 12.5.30 |

The current configured runtime database reported by Laravel Boost is PostgreSQL (`pgsql`). `php artisan migrate:status --no-interaction` completed read-only and reported all migrations through `2026_07_13_210000_create_secrets_table` as ran, with `2026_07_14_220000_add_is_admin_and_soft_deletes_to_users_table` pending.

## Evidence by area

### 1. Configuration and environment

**Portable foundation:** `config/database.php:20` selects the default driver from `DB_CONNECTION`. The `mysql` connection is already present at `config/database.php:47-65`, including host, port, database, credentials, charset, collation, strict mode, and PDO MySQL SSL option. PostgreSQL is configured separately at `config/database.php:87-100`.

This means the Laravel application can select MySQL through environment configuration **provided that**:

- `DB_CONNECTION=mysql` is set;
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` target a reachable MySQL server;
- the PHP runtime has `pdo_mysql` installed and enabled;
- the target database exists and the account can create/alter tables, indexes, and foreign keys.

**Important limitation:** deployment does not necessarily consume the repository `.env`. `.github/workflows/actions.yml:66-67` copies `.env.llamadev` to `.env` during deployment. Therefore changing only a local or production `.env` is not sufficient if the deployment process overwrites it or if the deployed server uses that committed environment template.

**Potential configuration gotcha:** `config/database.php:62-64` safely omits MySQL SSL options when `pdo_mysql` is absent, but omission of the extension means the connection itself cannot work. This must be checked on the actual PHP runtime, not only on the development machine.

### 2. Migrations and schema portability

Most migrations use Laravel's schema builder and standard column types:

- `database/migrations/0001_01_01_000000_create_users_table.php:14-37`
- `database/migrations/2026_07_07_003229_create_projects_table.php:13-23`
- `database/migrations/2026_07_07_010000_create_boards_table.php:13-28`
- `database/migrations/2026_07_07_011928_create_kanban_columns_table.php:13-30`
- `database/migrations/2026_07_07_015000_create_cards_table.php:30-59`
- `database/migrations/2026_07_07_030000_create_card_comments_table.php:31-40`
- `database/migrations/2026_07_07_040000_create_card_attachments_table.php:33-45`
- `database/migrations/2026_07_08_221048_create_kanban_labels_table.php:26-39`
- `database/migrations/2026_07_08_221049_create_kanban_card_label_table.php:25-37`
- `database/migrations/2026_07_11_010001_create_board_audit_logs_table.php:30-54`
- `database/migrations/2026_07_13_210000_create_secrets_table.php:13-25`
- `database/migrations/2026_07_14_220000_add_is_admin_and_soft_deletes_to_users_table.php:13-16`

These are generally supported by MySQL. Foreign keys, `unsignedBigInteger`-equivalent `foreignId`, text/longText, timestamps, JSON, indexes, unique indexes, and cascading/nulling foreign keys are all normal Laravel/MySQL use cases.

#### Blocking migration: active board-name uniqueness

`database/migrations/2026_07_11_010002_add_unique_index_board_name_active.php:32-63` branches by driver:

- PostgreSQL: `CREATE UNIQUE INDEX ... ON kanban_boards (project_id, LOWER(name)) WHERE deleted_at IS NULL` (`:36-41`). This is a native partial functional unique index.
- SQLite: the same statement is used (`:42-52`), matching the test database.
- Other drivers, including MySQL: `CREATE UNIQUE INDEX ... ON kanban_boards (project_id, LOWER(name))` (`:53-62`).

This fallback is not a real MySQL implementation of the stated requirement. MySQL has no PostgreSQL-style `WHERE deleted_at IS NULL` partial index syntax. Further, the `LOWER(name)` expression is not a portable ordinary index key definition in MySQL. Depending on the exact MySQL version, it can fail as invalid index syntax or require a functional-index-specific syntax/version/length treatment. The migration therefore cannot be treated as MySQL-compatible merely because it has an `else` branch.

The migration's own comments acknowledge the semantic compromise (`:55-58`): the fallback is intended to be full-table and prevents recycling a name from a soft-deleted board. Even if the expression were rewritten into MySQL syntax, that would still be a behavior change from PostgreSQL: the application rule at `app/Rules/UniqueActiveBoardName.php:50-53` explicitly ignores rows with non-null `deleted_at`.

**Required change:** a schema/code design is needed before MySQL cutover. Typical approaches include a generated normalized-name column plus a uniqueness strategy, or an application-level normalized value with a MySQL-compatible key. The choice must preserve the intended active-row semantics and handle concurrent inserts. This report does not change that code or schema.

#### Other migration considerations

- `database/migrations/2026_07_07_020000_add_slug_and_archived_at_to_projects_table.php:13-18` uses `after()`, which MySQL supports; PostgreSQL ignores column positioning. It is not a blocker, but migrations should be validated on the target server.
- `database/migrations/2026_07_07_050001_rename_boards_to_kanban_boards.php:14-17` and the related rename migrations rely on `Schema::rename()`. This is normally supported, but must be tested on the target MySQL version with existing foreign keys.
- `database/migrations/2026_07_11_010001_create_board_audit_logs_table.php:47-53` uses JSON and a `created_at` default of `useCurrent()`, both common MySQL features. Confirm the server version and SQL mode during validation.
- `database/migrations/2026_07_10_212730_normalise_kanban_positions.php:56-107` uses Eloquent reads and query-builder updates. Its comments claim byte-wise VARCHAR ordering across SQLite/MySQL/PostgreSQL (`:64-66`), but collation can affect string ordering in MySQL. The MySQL connection defaults to `utf8mb4_unicode_ci` (`config/database.php:56-57`), which is not a binary collation. This is a portability risk for ordering semantics and fractional position values, not an immediate syntax blocker.
- The same migration is intentionally irreversible (`:45-50`). A failed or partially completed cross-database migration cannot be safely rolled back by this migration's `down()` method.
- The migration uses application models (`:5-7`, `:56-107`) during schema/data migration. This is not inherently MySQL-specific, but it increases cutover sensitivity to model/table naming and to the schema being in the expected intermediate state.

### 3. Models, repositories, services, and controllers

No `app/Repositories` directory or repository classes were found. There are no separate database repository abstractions in the inspected tree; persistence is primarily through Eloquent models and controllers/services.

The model layer is mostly database-agnostic:

- `app/Models/User.php:24-27` uses standard `hasMany`.
- `app/Models/Project.php:80-86` uses a standard existence query for slug collision handling.
- `app/Models/KanbanCard.php:94-97` uses standard many-to-many pivot operations.
- `app/Models/KanbanBoardAuditLog.php:44-49` casts JSON payloads through Eloquent.
- `app/Models/Secret.php:26-30` uses Laravel's encrypted cast; this is application encryption and does not depend on PostgreSQL.

Controllers and services use standard Eloquent, transactions, pagination, relationships, and query-builder operations. Examples include `app/Http/Controllers/Api/V1/Kanban/CardController.php:69-81` and `:198-205`, and `app/Services/Kanban/BoardAuditLogger.php:38`.

#### Raw SQL findings

The application contains raw SQL expressions, but the identified production paths are simple and portable:

- `app/Rules/UniqueSecretKey.php:45-53`: `LOWER(key) = ?`.
- `app/Rules/UniqueActiveBoardName.php:50-53`: `LOWER(name) = ?` plus `whereNull`.
- `app/Http/Controllers/Api/V1/Kanban/CardController.php:63-64`: `whereRaw('1 = 0')`.

`LOWER()` is supported by MySQL, PostgreSQL, and SQLite for these comparisons. However, the expression's **index implementation** is not portable, which is why the migration remains a blocker even though the validation queries themselves are portable.

No application use of PostgreSQL-only operators or syntax such as `ILIKE`, `jsonb`, `RETURNING`, `ON CONFLICT`, PostgreSQL casts, or PostgreSQL-specific functions was identified in the inspected `app` code. No repositories containing hidden SQL were found.

### 4. Seeders and factories

Seeders use Eloquent model creation and relationships rather than database-specific SQL:

- `database/seeders/DatabaseSeeder.php:22-33`
- `database/seeders/DemoProjectSeeder.php` (model-based demo graph)
- `database/seeders/ExampleUserSeeder.php:36-45`

This is favorable for MySQL portability. The seeders do not solve migration compatibility: `db:seed` runs only after the schema has been created successfully.

The seeded position behavior was explicitly normalized by `database/migrations/2026_07_10_212730_normalise_kanban_positions.php`; because MySQL collation can affect lexicographic order, seeded and reordered positions should be validated on the actual MySQL collation selected for production.

### 5. Tests and test database

`phpunit.xml:20-28` forces tests to SQLite in-memory:

- `DB_CONNECTION=sqlite` (`:26`)
- `DB_DATABASE=:memory:` (`:27`)

`tests/Pest.php:18-20` applies `RefreshDatabase` to feature tests. This validates the SQLite branch of the board-name migration, not the MySQL branch. The board migration itself states that PostgreSQL and SQLite are the supported branches (`2026_07_11_010002_add_unique_index_board_name_active.php:14-20`) and provides only a fallback for other drivers.

This creates a significant false-confidence gap:

- The full test suite can remain green while a fresh MySQL migration fails.
- Existing tests include explicit SQLite assumptions, e.g. `tests/Feature/Kanban/CardTest.php:244-247`, which documents a SQLite storage quirk and says production is PostgreSQL.
- No MySQL service/container or MySQL test matrix was found in `.github/workflows/actions.yml`.
- The CI test job at `.github/workflows/actions.yml:8-38` installs PHP/Composer, copies `.env.example`, generates a key, and runs `composer test`; it does not install/configure PostgreSQL or MySQL and therefore relies on the PHPUnit SQLite overrides.

A MySQL cutover should not be approved based only on the current test suite. A target-version MySQL test run is required.

### 6. CI, deployment, and infrastructure

CI currently tests only the SQLite configuration described above. The deploy job is FTP-based (`.github/workflows/actions.yml:69-79`) and copies `.env.llamadev` (`:66-67`). The workflow does not:

- provision MySQL;
- run `php artisan migrate --force` against MySQL;
- run a MySQL compatibility smoke test;
- verify `pdo_mysql` availability;
- perform a database backup or restore check;
- implement a database cutover/rollback process.

The deployment workflow therefore cannot prove that a MySQL environment will work, even if the server's `.env` values are changed.

The Composer setup script runs `php artisan migrate --force` (`composer.json:41-47`), so a fresh deployment using MySQL will encounter the migration blocker during setup/migration unless the schema migration is made MySQL-compatible first.

### 7. Documentation and explicit product assumptions

`README.md:17-18` describes Laravel's ORM and schema migrations as database-agnostic, but it contains no supported-engine matrix or MySQL deployment procedure. `docs/kanban-api.md` documents API behavior and database-related concepts but does not establish a MySQL support contract.

The codebase has explicit PostgreSQL assumptions in comments/tests, including:

- `tests/Feature/Kanban/CardTest.php:247`: production is described as PostgreSQL.
- `database/migrations/2026_07_11_010002_add_unique_index_board_name_active.php:14-20`: only PostgreSQL and SQLite are described as supported for the central uniqueness feature.
- `database/migrations/2026_07_10_212730_normalise_kanban_positions.php:64-66`: ordering equivalence across engines is asserted without a MySQL collation-specific test.

These are not all executable blockers, but they are evidence that MySQL has not been established as a tested production target.

## Portability findings summary

### Confirmed portable or likely portable

- Laravel driver selection through `DB_CONNECTION`.
- Standard Eloquent models and relationships.
- Standard query-builder CRUD, pagination, transactions, and existence queries.
- Common scalar schema types: bigint IDs, varchar/string, text/longText, timestamps, booleans, nullable foreign keys.
- Standard foreign-key actions (`CASCADE`, `SET NULL`) when MySQL foreign-key support is enabled.
- JSON payload storage through Laravel's `json()` column and Eloquent array cast.
- Laravel encrypted casts, because encryption is performed by the application.
- Model-based seeders and factories.

### Requires remediation or explicit validation

| Severity | Finding | Evidence | Classification |
|---|---|---|---|
| Blocker | MySQL branch creates `LOWER(name)` unique index without a MySQL-specific implementation and without partial-index semantics | `database/migrations/2026_07_11_010002_add_unique_index_board_name_active.php:32-63` | Required code/schema change |
| High | Existing PostgreSQL data is not migrated by `.env` changes | Operational behavior; current DB is PostgreSQL | Required operational migration |
| High | Tests force SQLite and do not exercise MySQL | `phpunit.xml:20-28`, `tests/Pest.php:18-20` | Required test/infrastructure change for confidence |
| High | Deployment overwrites `.env` from `.env.llamadev` | `.github/workflows/actions.yml:66-67` | Required deployment/environment change |
| Medium | Position ordering depends on collation assumptions | `database/migrations/2026_07_10_212730_normalise_kanban_positions.php:64-66`; `config/database.php:56-57` | Required validation; possibly schema/config change |
| Medium | MySQL target version, SQL mode, charset, and collation are unspecified | `config/database.php:56-60` only provides defaults | Required infrastructure decision |
| Medium | Irreversible data normalization complicates rollback | `database/migrations/2026_07_10_212730_normalise_kanban_positions.php:45-50` | Required operational planning |
| Low | Documentation claims database agnosticism without engine support criteria | `README.md:17-18` | Documentation change recommended |

## What an environment-only change would and would not accomplish

### It would accomplish

- Select the existing `mysql` Laravel connection if `DB_CONNECTION=mysql` is loaded by the actual runtime.
- Direct ordinary Eloquent and query-builder calls to MySQL.
- Allow application boot to proceed if the PDO extension, credentials, database, and network are correct.

### It would not accomplish

- Create the MySQL schema.
- Convert PostgreSQL tables, sequences/identity values, indexes, constraints, or data types.
- Repair the active-board-name unique-index migration.
- Preserve the PostgreSQL partial-index behavior on MySQL.
- Make SQLite-only tests representative of MySQL.
- Change CI or deployment provisioning.
- Prevent `.github/workflows/actions.yml` from replacing `.env` with `.env.llamadev`.
- Validate MySQL collation-dependent ordering or production behavior.

## Required changes and operational steps

The following are required before claiming MySQL support. They are intentionally listed as recommendations only; no code was changed in this audit.

### Required code/schema work

1. Replace the driver fallback in `2026_07_11_010002_add_unique_index_board_name_active.php` with a MySQL-supported design that preserves the intended active-row, case-insensitive uniqueness semantics, or explicitly document and accept a different invariant.
2. Add a MySQL-specific migration/test covering duplicate active names, case-insensitive duplicates, reuse after soft delete, restore collisions, and concurrent insert behavior.
3. Decide and document the canonical charset/collation. If position strings must sort bytewise, use a compatible binary/ascii strategy or enforce/order explicitly rather than relying on the default `utf8mb4_unicode_ci` collation.
4. Review the historical migration sequence on a fresh MySQL database, including table renames with foreign keys and all pending migrations. Do not assume an already-migrated PostgreSQL database can be reused by changing the driver.

### Required infrastructure and deployment work

1. Select and record the exact MySQL version and server SQL mode.
2. Install/enable `pdo_mysql` for the PHP 8.3 runtime used by web, queue, CLI, and deployment processes.
3. Create the target database and least-privilege account with permissions for migrations and normal application operation.
4. Configure `DB_CONNECTION=mysql`, host, port, database, username, password, charset, and collation in the **actual deployment source**. Confirm whether `.env.llamadev` must be changed by the deployment owner; do not rely on a local `.env` that the workflow overwrites.
5. For an existing PostgreSQL installation, take a tested backup and perform a logical schema/data conversion. Validate encrypted secret values, IDs, foreign-key relationships, timestamps, indexes, and sequence/auto-increment continuity.
6. Run migrations in a disposable MySQL database before production. Do not run the irreversible position migration without a backup and a verified recovery plan.
7. Clear and rebuild cached configuration after changing environment values (`php artisan config:clear` and the deployment's approved config-cache procedure).
8. Run `php artisan migrate --force` only after the MySQL migration path has been fixed and tested.

## Validation checklist

### Static and configuration checks

- [ ] Confirm exact target engine: Oracle MySQL versus MariaDB.
- [ ] Confirm exact MySQL version and SQL mode.
- [ ] Confirm `pdo_mysql` is loaded in CLI, FPM/web, queue, and deployment runtimes.
- [ ] Confirm the effective runtime values with `php artisan config:show database.default` and the approved deployment diagnostics.
- [ ] Confirm deployment does not overwrite the intended settings with stale `.env.llamadev` values.
- [ ] Confirm no secret values are committed or exposed in logs.

### Fresh-schema checks

- [ ] Create an empty MySQL database.
- [ ] Run `php artisan migrate:fresh --force` or an equivalent disposable-database migration test.
- [ ] Verify every migration completes, especially `2026_07_11_010002_add_unique_index_board_name_active.php`.
- [ ] Verify all foreign keys and cascade/null-on-delete actions.
- [ ] Verify JSON payload round trips.
- [ ] Verify encrypted secret values round trip and remain unreadable at rest.
- [ ] Verify indexes with `SHOW CREATE TABLE` / `SHOW INDEX` or equivalent read-only inspection.

### Behavioral checks

- [ ] Create, update, archive, restore, and delete users, projects, boards, columns, cards, comments, attachments, labels, and secrets.
- [ ] Verify case-insensitive secret-key uniqueness.
- [ ] Verify active board-name uniqueness, soft-delete reuse, and restore collision semantics.
- [ ] Verify position ordering, reorder, append, and cross-column move under the selected collation.
- [ ] Verify pagination and ordering are stable.
- [ ] Verify foreign-key cascades and nullable actor/uploader/author behavior.
- [ ] Verify Sanctum token creation, lookup, and revocation.
- [ ] Run the complete Pest suite against MySQL, not only SQLite.
- [ ] Run API/Bruno smoke tests against the MySQL-backed application.

### Data migration and cutover checks

- [ ] Produce and restore-test a PostgreSQL backup.
- [ ] Convert/import data into a disposable MySQL database.
- [ ] Compare table row counts and key aggregates.
- [ ] Check every foreign-key orphan condition.
- [ ] Check uniqueness and duplicate normalization conditions.
- [ ] Check auto-increment values exceed current maximum IDs.
- [ ] Verify encrypted values with the same `APP_KEY`.
- [ ] Verify file attachments separately; database rows do not migrate files stored on disk.
- [ ] Define downtime/read-only window or replication strategy.
- [ ] Define rollback criteria and a tested rollback path.
- [ ] Monitor application, queue, and database logs after cutover.

## Final recommendation

Do **not** approve a production PostgreSQL-to-MySQL switch as an `.env`-only operation. The minimum safe path is: implement and test the MySQL-compatible active-board uniqueness schema, execute the complete migration history on the exact target MySQL version, add MySQL CI coverage, verify deployment environment handling, and perform a backup-backed data conversion rehearsal. The current Eloquent-heavy application is a good portability foundation, but the repository does not currently provide evidence that MySQL is a supported, tested, or operationally deployable target.

## References inspected

- `config/database.php`
- `composer.json`, `composer.lock`
- `.env.example` and `.env.llamadev` (DB key presence only; values not read)
- `database/migrations/*.php`
- `app/Models/*.php`
- `app/Rules/UniqueSecretKey.php`
- `app/Rules/UniqueActiveBoardName.php`
- `app/Services/Kanban/BoardAuditLogger.php`
- `app/Http/Controllers/Api/V1/**/*.php`
- `database/seeders/*.php`
- `tests/Pest.php`, `phpunit.xml`, `tests/**/*.php`
- `.github/workflows/actions.yml`
- `README.md`, `docs/kanban-api.md`
- Laravel 13 database, migration, and testing documentation returned by Laravel Boost
- Read-only `php artisan migrate:status --no-interaction`

