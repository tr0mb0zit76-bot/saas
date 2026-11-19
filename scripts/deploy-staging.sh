#!/usr/bin/env bash
# Deploy Traklo Pro to a Linux staging host (ADR-014).
# Usage (from app root on the server):
#   bash scripts/deploy-staging.sh [git-ref]
# Default ref: main
set -euo pipefail

REF="${1:-main}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  echo "Missing .env — copy .env.staging.example and fill secrets first." >&2
  exit 1
fi

APP_ENV="$(grep -E '^APP_ENV=' .env | head -1 | cut -d= -f2- | tr -d '"' || true)"
if [[ "$APP_ENV" == "local" ]]; then
  echo "Refusing deploy: APP_ENV=local (lab). Use staging/production .env." >&2
  exit 1
fi

echo "==> git fetch / checkout ${REF}"
git fetch --prune origin
git checkout "$REF"
git pull --ff-only origin "$REF" 2>/dev/null || git reset --hard "origin/${REF}"

echo "==> composer"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> frontend"
npm ci
npm run build

echo "==> migrate"
php artisan migrate --force

echo "==> storage link"
php artisan storage:link --force 2>/dev/null || php artisan storage:link || true

echo "==> caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache 2>/dev/null || true

echo "==> restart workers (best-effort)"
if command -v supervisorctl >/dev/null 2>&1; then
  supervisorctl restart all || true
fi
if command -v systemctl >/dev/null 2>&1; then
  systemctl reload php8.3-fpm 2>/dev/null || systemctl reload php-fpm 2>/dev/null || true
fi

echo "==> health"
php artisan about --only=environment 2>/dev/null || php artisan --version
curl -fsS -o /dev/null -w "HTTP %{http_code}\n" "${APP_URL:-https://localhost}/up" || true

echo "Deploy done. Run browser smoke: docs/sync/browser-smoke-howto.md"
