#!/usr/bin/env bash
# scripts/dump-dev-db.sh
# Dumps the development database to database/backups/dev/<db>_<YYYYMMDD>_<HHMMSS>.dump
# Uses pg_dump from MacPorts (PostgreSQL 18) with custom format + gzip.

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
# Build output filename: <dbname>_YYYYMMDD_HHMMSS.dump
# ---------------------------------------------------------------------------
mkdir -p "${BACKUP_DIR}"

TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
# Sanitize DB name for filesystem (strip whitespace, keep alnum/_-.)
SAFE_DB_NAME="$(printf '%s' "${DB_DATABASE}" | tr -d '[:space:]')"
OUTPUT_FILE="${BACKUP_DIR}/${SAFE_DB_NAME}_${TIMESTAMP}.dump"

echo "==> Dumping '${DB_DATABASE}' from ${DB_HOST}:${DB_PORT}"
echo "==> Using ${PG_DUMP}"
echo "==> Writing ${OUTPUT_FILE}"

# ---------------------------------------------------------------------------
# Run pg_dump.
#   -Fc  : custom format (compressed, supports selective restore)
#   -Z0  : no extra compression (custom format already compresses)
#   PGPASSWORD is passed via env to avoid leaking in `ps`
# ---------------------------------------------------------------------------
PGPASSWORD="${DB_PASSWORD}" "${PG_DUMP}" \
  --host="${DB_HOST}" \
  --port="${DB_PORT}" \
  --username="${DB_USERNAME}" \
  --dbname="${DB_DATABASE}" \
  --no-owner \
  --clean \
  --if-exists \
  --format=custom \
  --compress=0 \
  --file="${OUTPUT_FILE}"

echo "==> Done. $(du -h "${OUTPUT_FILE}" | cut -f1) at ${OUTPUT_FILE}"
