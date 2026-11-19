#!/usr/bin/env bash
set -euo pipefail

APP=/var/www/www-root/data/www/traklo.pro
BRANCH=cursor/traklo-session-integrate-9d42
BUNDLE=/tmp/traklo-deploy.bundle
REMOTE=git@github.com:tr0mb0zit76-bot/saas.git

cd "$APP"
cp -a .env /home/admtr4kl0/traklo.env.bak

if [[ ! -d .git ]]; then
  git init -b "$BRANCH"
fi

git remote remove origin 2>/dev/null || true
git remote add origin "$REMOTE"

git fetch "$BUNDLE" "${BRANCH}:refs/remotes/origin/${BRANCH}"
git checkout -f -B "$BRANCH" "origin/${BRANCH}"

# Keep production secrets and writable storage.
cp -a /home/admtr4kl0/traklo.env.bak .env
chmod 640 .env || true

# Ensure deploy script is executable.
chmod +x scripts/deploy-prod.sh 2>/dev/null || true

git status -sb
git rev-parse --short HEAD
git remote -v
test -f scripts/deploy-prod.sh
rm -f "$BUNDLE"
echo GIT_INIT_OK
