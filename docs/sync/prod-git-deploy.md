# Prod deploy via git (traklo.pro)

**Host:** `admtr4kl0@89.111.169.211`  
**App:** `/var/www/www-root/data/www/traklo.pro`  
**Remote:** `git@github.com:tr0mb0zit76-bot/saas.git` (SSH; ключ аккаунта `traklo.pro-deploy`)  
**Branch (now):** `cursor/traklo-session-integrate-9d42` (пока не смержено в `main`)

## SSH-ключ на сервере (git pull с GitHub)

`~/.ssh/traklo_deploy` → SSH keys аккаунта `tr0mb0zit76-bot` (не Deploy keys). Fingerprint `SHA256:oMUv9L9rRtDPziZrJQU/F2utHwxB1gSiU/54mt3K7Jc`.

Проверка:

```bash
ssh -T git@github.com
# Hi tr0mb0zit76-bot! You've successfully authenticated...
cd /var/www/www-root/data/www/traklo.pro && git fetch origin
```

## GitHub Actions CD

Workflow: `.github/workflows/deploy-prod.yml`  
Триггер: push в `main` или `cursor/traklo-session-integrate-9d42`, либо `workflow_dispatch`.

Secrets (repo): `PROD_HOST`, `PROD_USER`, `PROD_APP_DIR`, `PROD_SSH_KEY`, `PROD_SSH_FINGERPRINT`.  
Environment: `production`.

CI SSH-ключ (отдельный от git-ключа сервера) лежит в `authorized_keys` пользователя `admtr4kl0`. Actions заходит по SSH и запускает `bash scripts/deploy-prod.sh <ref>`.

Ручной прогон:

```bash
gh workflow run deploy-prod.yml -R tr0mb0zit76-bot/saas --ref cursor/traklo-session-integrate-9d42
```

## Обычный деплой (с этой машины)

```powershell
pwsh -File scripts/deploy-prod.ps1
```

Скрипт: `git push` → на сервере `git pull` (или bundle fallback) → `composer` / `npm run build` / `migrate` / caches.

## Только на сервере (когда deploy key уже есть)

```bash
cd /var/www/www-root/data/www/traklo.pro
bash scripts/deploy-prod.sh cursor/traklo-session-integrate-9d42
```

`.env` и `storage/` в git не входят — не затираются.
