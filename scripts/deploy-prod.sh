#!/usr/bin/env bash
# Deploy Traklo Pro on the production host (ISPmanager path).
# Usage (on the server, from app root):
#   bash scripts/deploy-prod.sh [git-ref]
# Default ref: current upstream branch, or main.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  echo "Missing .env — refuse to deploy." >&2
  exit 1
fi

APP_ENV="$(grep -E '^APP_ENV=' .env | head -1 | cut -d= -f2- | tr -d '"' || true)"
if [[ "$APP_ENV" == "local" || "$APP_ENV" == "testing" ]]; then
  echo "Refusing deploy: APP_ENV=${APP_ENV}." >&2
  exit 1
fi

if [[ ! -d .git ]]; then
  echo "Missing .git — init production git first." >&2
  exit 1
fi

REF="${1:-}"
if [[ -z "$REF" ]]; then
  REF="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || true)"
  if [[ -z "$REF" || "$REF" == "HEAD" ]]; then
    REF="main"
  fi
fi

PHP_BIN="${PHP_BIN:-/usr/bin/php}"

echo "==> git fetch / reset ${REF}"
git fetch --prune origin
git checkout "$REF"
git reset --hard "origin/${REF}"

echo "==> composer"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> frontend"
if [[ -f package-lock.json ]]; then
  npm ci
else
  npm install
fi
npm run build

echo "==> migrate"
"$PHP_BIN" artisan migrate --force

echo "==> storage link"
"$PHP_BIN" artisan storage:link --force 2>/dev/null || "$PHP_BIN" artisan storage:link || true

echo "==> caches"
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache 2>/dev/null || true

echo "==> restart workers (best-effort)"
if command -v supervisorctl >/dev/null 2>&1; then
  sudo supervisorctl restart all || supervisorctl restart all || true
fi
if command -v systemctl >/dev/null 2>&1; then
  sudo systemctl reload php8.3-fpm 2>/dev/null || sudo systemctl reload php-fpm 2>/dev/null || true
fi

echo "==> health"
"$PHP_BIN" artisan --version
curl -fsS -o /dev/null -w "HTTP %{http_code}\n" "${APP_URL:-https://traklo.pro}/up" || true

echo "Deploy done."
