# Prod deploy via git (traklo.pro)

**Host:** `admtr4kl0@89.111.169.211`  
**App:** `/var/www/www-root/data/www/traklo.pro`  
**Remote:** `git@github.com:tr0mb0zit76-bot/saas.git`  
**Branch (now):** `cursor/traklo-session-integrate-9d42` (пока не смержено в `main`)

## Разовый шаг: deploy key

На сервере уже есть ключ `~/.ssh/traklo_deploy`. Добавьте **public** key в GitHub:

**Repo → Settings → Deploy keys → Add deploy key** (read-only):

```
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOQqECoCqKFdt7r1BwNuKfKTIExNowWIbx+srTCXQhvE traklo.pro-deploy
```

Проверка на сервере:

```bash
ssh -T git@github.com
cd /var/www/www-root/data/www/traklo.pro && git fetch origin
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
