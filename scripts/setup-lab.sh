#!/usr/bin/env bash
# One-command SaaS lab setup (Linux/bash). Mirrors setup-lab.ps1.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
V5_ROOT="${V5_ROOT:-/tmp/v5.local}"
SKIP_BOOTSTRAP="${SKIP_BOOTSTRAP:-0}"

cd "$REPO_ROOT"

echo "=== SaaS Lab Setup (bash) ==="

if [[ ! -d "$V5_ROOT/artisan" && ! -f "$V5_ROOT/artisan" ]]; then
  echo "Cloning v5 source to $V5_ROOT ..."
  git clone --depth 1 https://github.com/tr0mb0zit76-bot/v5.git "$V5_ROOT"
fi

if [[ "$SKIP_BOOTSTRAP" != "1" && ! -f "$REPO_ROOT/artisan" ]]; then
  echo "[M1] Bootstrap from v5 ..."
  for d in app bootstrap config database lang public resources routes tests; do
    rm -rf "$REPO_ROOT/$d"
    cp -a "$V5_ROOT/$d" "$REPO_ROOT/"
  done
  for f in artisan composer.json composer.lock package.json package-lock.json vite.config.js tailwind.config.js postcss.config.js phpunit.xml .env.example; do
    cp -a "$V5_ROOT/$f" "$REPO_ROOT/" 2>/dev/null || true
  done
fi

mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache

if [[ ! -f .env && -f .env.example ]]; then
  cp .env.example .env
fi

if [[ -x scripts/apply-saas-lab-env.sh ]]; then
  bash scripts/apply-saas-lab-env.sh "${SAAS_LAB_HOST:-saas.local}"
fi

if grep -q '^DB_CONNECTION=sqlite' .env 2>/dev/null || ! grep -q '^DB_DATABASE=' .env 2>/dev/null; then
  cat >> .env <<'EOF'
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=saas_crm
DB_USERNAME=saas
DB_PASSWORD=saas_dev
EOF
fi

composer install --no-interaction
npm ci

php artisan key:generate --force 2>/dev/null || true
php artisan migrate --force --schema-path=database/schema/.skip-mysql-cli-load
php artisan db:seed --class=SaasDemoSeeder --force
php artisan db:seed --class=TenantDemoSeeder --force
php artisan saas:smoke-lab
npm run build

echo "=== Done ==="
echo "Login: admin@saas.local / password"
echo "php artisan serve --host=127.0.0.1 --port=8000"
