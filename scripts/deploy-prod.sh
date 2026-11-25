#!/usr/bin/env bash
# Deploy Traklo Pro on the production host (ISPmanager path).
# Usage (on the server, from app root):
#   bash scripts/deploy-prod.sh [git-ref]
#   WITH_MAINTENANCE=1 bash scripts/deploy-prod.sh [git-ref]   # rare schema-breaking deploys
# Default ref: current upstream branch, or main.
#
# Why no artisan down by default: shared host — down brands platform + every tenant
# with Laravel 503. Routine pull/composer/build/migrate/cache stays online.
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
CONFIG_CACHE="bootstrap/cache/config.php"
CONFIG_BACKUP="$(mktemp)"
# Opt-in only: WITH_MAINTENANCE=1 for breaking schema deploys. Default stays live (no 503).
WITH_MAINTENANCE="${WITH_MAINTENANCE:-0}"
MAINTENANCE=0

cleanup() {
  local code=$?
  if [[ "$MAINTENANCE" -eq 1 ]]; then
    "$PHP_BIN" artisan up >/dev/null 2>&1 || true
  fi
  rm -f "$CONFIG_BACKUP"
  exit "$code"
}
trap cleanup EXIT

reload_php_fpm() {
  if command -v systemctl >/dev/null 2>&1; then
    sudo systemctl reload php8.3-fpm 2>/dev/null || sudo systemctl reload php-fpm 2>/dev/null || true
  fi
}

verify_cached_config() {
  "$PHP_BIN" artisan tinker --execute="
    \$db = (string) config('database.default');
    \$key = (string) config('app.key');
    if (\$db !== 'mysql' || \$key === '') {
      fwrite(STDERR, \"Bad config cache: database.default={\$db}, app.key_len=\".strlen(\$key).PHP_EOL);
      exit(1);
    }
    echo \"config_ok db={\$db}\".PHP_EOL;
  "
}

if [[ "${SKIP_GIT:-0}" == "1" ]]; then
  echo "==> skip git update (SKIP_GIT=1), HEAD=$(git rev-parse --short HEAD)"
else
  echo "==> git fetch / reset ${REF}"
  git fetch --prune origin
  git checkout "$REF"
  git reset --hard "origin/${REF}"
fi

if [[ "$WITH_MAINTENANCE" == "1" ]]; then
  echo "==> maintenance on (WITH_MAINTENANCE=1)"
  "$PHP_BIN" artisan down --retry=30 --secret=traklo-deploy >/dev/null
  MAINTENANCE=1
else
  echo "==> skip maintenance (set WITH_MAINTENANCE=1 for schema-breaking deploys)"
  # Clear leftover down from an interrupted prior WITH_MAINTENANCE deploy.
  "$PHP_BIN" artisan up >/dev/null 2>&1 || true
fi

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
if [[ -f "$CONFIG_CACHE" ]]; then
  cp -a "$CONFIG_CACHE" "$CONFIG_BACKUP"
fi
# config:cache clears then rewrites config.php — brief window; backup+verify below.
"$PHP_BIN" artisan config:cache
if ! verify_cached_config; then
  echo "config:cache produced unusable config — restoring previous cache" >&2
  if [[ -s "$CONFIG_BACKUP" ]]; then
    cp -a "$CONFIG_BACKUP" "$CONFIG_CACHE"
  else
    echo "No previous config.php backup to restore." >&2
    exit 1
  fi
  verify_cached_config
fi
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache 2>/dev/null || true

echo "==> reload php-fpm (drop stale opcache of config.php)"
reload_php_fpm

echo "==> restart workers (best-effort)"
if command -v supervisorctl >/dev/null 2>&1; then
  sudo supervisorctl restart all || supervisorctl restart all || true
fi

if [[ "$MAINTENANCE" -eq 1 ]]; then
  echo "==> maintenance off"
  "$PHP_BIN" artisan up
  MAINTENANCE=0
fi

echo "==> health"
"$PHP_BIN" artisan --version
curl -fsS -o /dev/null -w "HTTP %{http_code}\n" "${APP_URL:-https://traklo.pro}/up" || true

echo "Deploy done."
echo "DEPLOY_OK"
