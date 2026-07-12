#!/usr/bin/env bash
# SaaS lab .env — Traklo Pro demo + platform (mirrors apply-saas-lab-env.ps1).
# Usage: ./scripts/apply-saas-lab-env.sh [host]
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HOST_NAME="${1:-saas.local}"
ENV_FILE="$REPO_ROOT/.env"

if [[ ! -f "$ENV_FILE" ]]; then
  cp "$REPO_ROOT/.env.example" "$ENV_FILE"
fi

upsert() {
  local key="$1"
  local val="$2"
  if grep -q "^${key}=" "$ENV_FILE"; then
    sed -i "s|^${key}=.*|${key}=${val}|" "$ENV_FILE"
  else
    echo "${key}=${val}" >> "$ENV_FILE"
  fi
}

APP_URL="http://${HOST_NAME}"

upsert APP_NAME '"Traklo Pro"'
upsert APP_URL "$APP_URL"
upsert CRM_DOMAIN "$HOST_NAME"
upsert SHOWCASE_DOMAIN "$HOST_NAME"
upsert SHOWCASE_MODE traklo_pro
upsert SAAS_DEFAULT_TENANT_SLUG demo
upsert SAAS_PLATFORM_ADMIN_EMAILS 'admin@saas.local,platform-admin@saas.local'
upsert SAAS_TRIAL_DAYS 14
upsert SAAS_DEMO_SIGNUP_ENABLED true
upsert PLATFORM_DOMAIN "platform.${HOST_NAME}"
upsert SESSION_SECURE_COOKIE false
upsert TENANT_STORAGE_DISK tenant_local
upsert TENANT_STORAGE_FOR_DOCUMENTS true

echo "Updated .env for SaaS lab host: $HOST_NAME"
echo "  SAAS_DEMO_SIGNUP_ENABLED=true"
echo "  SHOWCASE_MODE=traklo_pro"

if [[ -f "$REPO_ROOT/artisan" ]]; then
  php "$REPO_ROOT/artisan" config:clear
  php "$REPO_ROOT/artisan" route:clear
fi
