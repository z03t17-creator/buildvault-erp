#!/usr/bin/env bash
# Render web service entrypoint — migrate, slim seed, bind PORT quickly.
set -euo pipefail
cd /app

# Render injects RENDER_EXTERNAL_URL once the service has a hostname.
if [[ -z "${APP_URL:-}" && -n "${RENDER_EXTERNAL_URL:-}" ]]; then
  export APP_URL="${RENDER_EXTERNAL_URL}"
fi
export APP_URL="${APP_URL:-http://localhost:10000}"

# Prefer Render Postgres connection string (also maps to Laravel DB_URL).
if [[ -n "${DATABASE_URL:-}" ]]; then
  export DB_URL="${DB_URL:-$DATABASE_URL}"
fi
export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
export DB_SSLMODE="${DB_SSLMODE:-require}"

# Session / cache / queue on DB (no Redis required).
export CACHE_STORE="${CACHE_STORE:-database}"
export SESSION_DRIVER="${SESSION_DRIVER:-database}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"

# Demo seeders are heavy — default off on Render so we reach /up within the health window.
export SEED_DEMO="${SEED_DEMO:-false}"

# Laravel APP_KEY must be base64:… — regenerate if missing/invalid.
# Prefer a stable APP_KEY from Render env (Blueprint generateValue or manual).
if [[ -z "${APP_KEY:-}" || "${APP_KEY}" != base64:* ]]; then
  export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
  echo "WARNING: Generated ephemeral APP_KEY for this boot — set a stable APP_KEY in Render env for session persistence."
fi

# Writable dirs on ephemeral FS
mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/public storage/app/backups bootstrap/cache
chmod -R 775 storage bootstrap/cache || true

php artisan package:discover --ansi || true
php artisan storage:link --force || true

echo "Running migrations…"
php artisan migrate --force --no-interaction

echo "Seeding core roles/admin/vault (SEED_DEMO=${SEED_DEMO})…"
php artisan db:seed --force --no-interaction

php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

PORT="${PORT:-10000}"
echo "Starting BuildVault ERP on 0.0.0.0:${PORT} (APP_URL=${APP_URL})"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
