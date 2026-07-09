#!/usr/bin/env bash
# scripts/dump-dev-db.sh
# Dumps the development database to database/backups/dev/<db>_<YYYYMMDD>_<HHMMSS>.sql
# Uses pg_dump from MacPorts (PostgreSQL 18) with plain text format so the file
# can be loaded by a restricted pgAdmin ("Run SQL script" / psql-style execution).

set -euo pipefail

# ---------------------------------------------------------------------------
# Resolve script directory regardless of where `make` was invoked from.
# ---------------------------------------------------------------------------
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd -- "${SCRIPT_DIR}/.." && pwd)"
BACKUP_DIR="${PROJECT_ROOT}/database/backups/dev"
ENV_FILE="${PROJECT_ROOT}/.env"

# ---------------------------------------------------------------------------
# Locate pg_dump. MacPorts installs it under /opt/local/lib/postgresql<ver>/bin
# without symlinking it into /opt/local/bin, so we can't rely on PATH alone.
# ---------------------------------------------------------------------------
PG_DUMP=""
for candidate in \
  /opt/local/lib/postgresql18/bin/pg_dump \
  /opt/local/bin/pg_dump \
  "$(command -v pg_dump 2>/dev/null || true)"
do
  if [[ -n "${candidate}" && -x "${candidate}" ]]; then
    PG_DUMP="${candidate}"
    break
  fi
done

if [[ -z "${PG_DUMP}" ]]; then
  echo "ERROR: pg_dump not found. Install it with: sudo port install postgresql18 +client_only" >&2
  exit 1
fi

# ---------------------------------------------------------------------------
# Read DB_* from .env without exporting the rest. We only want DB_* so we don't
# leak secrets like APP_KEY into the dump command environment.
# ---------------------------------------------------------------------------
if [[ ! -f "${ENV_FILE}" ]]; then
  echo "ERROR: ${ENV_FILE} not found." >&2
  exit 1
fi

# Parse KEY=VALUE lines. Strip optional surrounding quotes; ignore comments
# and blank lines. Trims a literal trailing space inside the value (a known
# foot-gun in some .env files).
get_env() {
  local key="$1"
  awk -F= -v k="${key}" '
    $0 !~ /^[[:space:]]*#/ && $1 == k {
      val = $2
      sub(/^[[:space:]]+/, "", val)
      sub(/[[:space:]]+$/, "", val)
      gsub(/^['"'"'"]|['"'"'"]$/, "", val)
      print val
      exit
    }
  ' "${ENV_FILE}"
}

DB_CONNECTION="$(get_env DB_CONNECTION)"
DB_HOST="$(get_env DB_HOST)"
DB_PORT="$(get_env DB_PORT)"
DB_DATABASE="$(get_env DB_DATABASE)"
DB_USERNAME="$(get_env DB_USERNAME)"
DB_PASSWORD="$(get_env DB_PASSWORD)"

if [[ "${DB_CONNECTION}" != "pgsql" && "${DB_CONNECTION}" != "postgres" && "${DB_CONNECTION}" != "postgresql" ]]; then
  echo "ERROR: DB_CONNECTION='${DB_CONNECTION}' is not PostgreSQL. This script only supports pgsql." >&2
  exit 1
fi

for var in DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD; do
  if [[ -z "${!var}" ]]; then
    echo "ERROR: ${var} is empty in ${ENV_FILE}." >&2
    exit 1
  fi
done

# ---------------------------------------------------------------------------
# Build output filename: <dbname>_YYYYMMDD_HHMMSS.sql
# ---------------------------------------------------------------------------
mkdir -p "${BACKUP_DIR}"

TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
# Sanitize DB name for filesystem (strip whitespace, keep alnum/_-.)
SAFE_DB_NAME="$(printf '%s' "${DB_DATABASE}" | tr -d '[:space:]')"
OUTPUT_FILE="${BACKUP_DIR}/${SAFE_DB_NAME}_${TIMESTAMP}.sql"

echo "==> Dumping '${DB_DATABASE}' from ${DB_HOST}:${DB_PORT}"
echo "==> Using ${PG_DUMP}"
echo "==> Writing ${OUTPUT_FILE}"

# ---------------------------------------------------------------------------
# Run pg_dump.
#   --format=plain : emits a single plain-text SQL script. This is the only
#                    format the restricted pgAdmin "Run SQL script" / psql
#                    execution path can ingest; custom/directory formats
#                    require pg_restore which is not available.
#   --no-owner     : skip ownership commands (we are applying on prod where
#                    the role likely differs).
#   --clean --if-exists : emit DROP ... IF EXISTS before CREATE, so the script
#                    can be run against a non-empty schema. pgAdmin's
#                    restricted uploader often runs the script in a single
#                    transaction; this keeps it idempotent-ish.
#   PGPASSWORD     : passed via env to avoid leaking in `ps`.
# ---------------------------------------------------------------------------
PGPASSWORD="${DB_PASSWORD}" "${PG_DUMP}" \
  --host="${DB_HOST}" \
  --port="${DB_PORT}" \
  --username="${DB_USERNAME}" \
  --dbname="${DB_DATABASE}" \
  --no-owner \
  --clean \
  --if-exists \
  --format=plain \
  --file="${OUTPUT_FILE}"

# ---------------------------------------------------------------------------
# Strip ownership statements the target user cannot run.
#
# The target server is a managed PG 10.x (cPanel/pgAdmin) where the application
# role is NOT the owner of the `public` schema, the `plpgsql` extension, or any
# database-level object. pg_dump with --clean --if-exists --no-owner still emits:
#
#   - DROP / CREATE / COMMENT on the plpgsql extension       -> "must be owner
#                                                              of extension
#                                                              plpgsql"
#   - DROP / CREATE / ALTER SCHEMA public OWNER TO ...      -> "must be owner
#                                                              of schema public"
#   - COMMENT ON SCHEMA public IS ...                       -> same
#
# plpgsql and the `public` schema are preinstalled in every PG cluster, so we
# drop those lines in-place with sed. The diff is tiny and idempotent.
# ---------------------------------------------------------------------------
sed -i.bak \
  -e '/^DROP EXTENSION IF EXISTS plpgsql;$/d' \
  -e '/^CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;$/d' \
  -e '/^COMMENT ON EXTENSION plpgsql IS /d' \
  -e '/^DROP SCHEMA IF EXISTS public;$/d' \
  -e '/^CREATE SCHEMA public;$/d' \
  -e '/^ALTER SCHEMA public OWNER TO /d' \
  -e '/^COMMENT ON SCHEMA public IS /d' \
  "${OUTPUT_FILE}"
rm -f "${OUTPUT_FILE}.bak"

echo "==> Done. $(du -h "${OUTPUT_FILE}" | cut -f1) at ${OUTPUT_FILE}"
