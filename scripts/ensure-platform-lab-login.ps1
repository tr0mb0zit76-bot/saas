# One-shot: восстановить platform login на lab после migrate/seed сброса.
#   pwsh -File scripts/ensure-platform-lab-login.ps1

$ErrorActionPreference = 'Stop'
$repoRoot = if (Test-Path (Join-Path $PSScriptRoot '..\artisan')) {
    (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
} else {
    'C:\OSPanel\home\saas\saas.local'
}
Set-Location $repoRoot

$envFile = Join-Path $repoRoot '.env'

function Set-EnvKey {
    param([string]$Key, [string]$Value)
    if (-not (Test-Path $envFile)) { return }
    $lines = Get-Content $envFile
    $idx = [array]::FindIndex($lines, [Predicate[string]] { param($l) $l -match "^$Key=" })
    if ($idx -ge 0) { $lines[$idx] = "$Key=$Value" } else { $lines += "$Key=$Value" }
    Set-Content -Path $envFile -Value ($lines -join "`n")
}

Write-Host 'Platform lab login repair' -ForegroundColor Cyan

Set-EnvKey 'SAAS_PLATFORM_ADMIN_EMAILS' 'admin@saas.local,platform-admin@saas.local'
Set-EnvKey 'SESSION_SECURE_COOKIE' 'false'

php artisan config:clear | Out-Null
php artisan route:clear | Out-Null
php artisan db:seed --class=SaasDemoSeeder --force 2>&1 | Out-Host

if (Test-Path (Join-Path $repoRoot 'scripts/check-platform-login.php')) {
    php (Join-Path $repoRoot 'scripts/check-platform-login.php') 2>&1 | Out-Host
}

Write-Host ''
Write-Host 'Login (Chrome/Edge/Firefox):' -ForegroundColor Green
Write-Host '  http://platform.saas.local/login' -ForegroundColor Green
Write-Host '  admin@saas.local / password' -ForegroundColor Green
Write-Host ''
Write-Host 'Cursor Simple Browser: если всё ещё 419 — известное ограничение HttpOnly cookies; используйте внешний браузер.' -ForegroundColor Yellow
