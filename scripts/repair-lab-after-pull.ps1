# Быстрое восстановление lab после git pull (503 на логин, пустая главная).
#
# Типичные причины:
#   - CRM_DOMAIN из .env.example (crm.avtoaliyans.local) вместо saas.local → 503 на ссылке «Войти»
#   - public/hot от npm run dev → белая страница без Vite
#   - нет npm run build / migrate после pull
#
# Usage:
#   pwsh -File scripts/repair-lab-after-pull.ps1
#   pwsh -File scripts/repair-lab-after-pull.ps1 -HostName saas.local -Full

param(
    [string]$HostName = 'saas.local',
    [switch]$Full
)

$ErrorActionPreference = 'Stop'
$repoRoot = 'C:\OSPanel\home\saas\saas.local'
if (-not (Test-Path (Join-Path $repoRoot 'artisan'))) {
    $repoRoot = Split-Path -Parent $PSScriptRoot
}
Set-Location $repoRoot

function Add-OspanelPath {
    $ospanel = if (Test-Path 'C:\OSPanel') { 'C:\OSPanel' } else { 'C:\ospanel' }
    if (-not (Test-Path $ospanel)) { return }

    Get-ChildItem -Path "$ospanel\modules\php" -Directory -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match '^PHP-8\.3' } |
        Sort-Object Name -Descending |
        Select-Object -First 1 |
        ForEach-Object { $env:Path = "$($_.FullName);$env:Path" }
}

function Read-EnvValue {
    param([string]$Key)
    $line = Get-Content (Join-Path $repoRoot '.env') -ErrorAction SilentlyContinue |
        Where-Object { $_ -match "^$Key=" } |
        Select-Object -First 1
    if (-not $line) { return $null }
    return ($line -split '=', 2)[1]
}

function Test-HttpStatus {
    param([string]$Url)
    try {
        $r = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 10
        return [int]$r.StatusCode
    } catch {
        if ($_.Exception.Response) {
            return [int]$_.Exception.Response.StatusCode.value__
        }
        return 0
    }
}

Add-OspanelPath

Write-Host "=== SaaS lab repair ($repoRoot) ===" -ForegroundColor Cyan

$hotFile = Join-Path $repoRoot 'public\hot'
if (Test-Path $hotFile) {
    Remove-Item $hotFile -Force
    Write-Host 'Removed public/hot (stale Vite dev pointer).' -ForegroundColor Yellow
}

& (Join-Path $repoRoot 'scripts\apply-saas-lab-env.ps1') -HostName $HostName

php artisan config:clear
php artisan route:clear
php artisan view:clear

if ($Full) {
    Write-Host 'Full repair: migrate + build...' -ForegroundColor Cyan
    php artisan migrate --force
    npm run build
} elseif (-not (Test-Path (Join-Path $repoRoot 'public\build\manifest.json'))) {
    Write-Host 'public/build/manifest.json missing — running npm run build...' -ForegroundColor Yellow
    npm run build
}

$appUrl = Read-EnvValue 'APP_URL'
$crmDomain = Read-EnvValue 'CRM_DOMAIN'
$showcaseDomain = Read-EnvValue 'SHOWCASE_DOMAIN'
$platformDomain = Read-EnvValue 'PLATFORM_DOMAIN'

Write-Host ''
Write-Host '.env hosts:' -ForegroundColor Cyan
Write-Host "  APP_URL=$appUrl"
Write-Host "  CRM_DOMAIN=$crmDomain"
Write-Host "  SHOWCASE_DOMAIN=$showcaseDomain"
Write-Host "  PLATFORM_DOMAIN=$platformDomain"

if ($crmDomain -and $showcaseDomain -and $crmDomain -ne $showcaseDomain) {
    Write-Host ''
    Write-Host 'WARNING: CRM_DOMAIN != SHOWCASE_DOMAIN — ссылка «Войти» ведёт на другой хост.' -ForegroundColor Red
    Write-Host '  Для lab оба должны быть saas.local. Запустите apply-saas-lab-env.ps1 или этот скрипт снова.' -ForegroundColor Red
}

$base = if ($appUrl) { $appUrl.TrimEnd('/') } else { "http://$HostName" }
$checks = @(
    @{ Name = 'landing'; Url = "$base/" },
    @{ Name = 'login'; Url = "$base/login" },
    @{ Name = 'platform login'; Url = "http://platform.$HostName/login" }
)

Write-Host ''
Write-Host 'HTTP checks:' -ForegroundColor Cyan
foreach ($c in $checks) {
    $code = Test-HttpStatus $c.Url
    $color = if ($code -ge 200 -and $code -lt 400) { 'Green' } else { 'Red' }
    Write-Host ("  {0,-16} {1} -> {2}" -f $c.Name, $c.Url, $code) -ForegroundColor $color
}

Write-Host ''
Write-Host 'Login: admin@saas.local / password' -ForegroundColor Green
Write-Host 'Platform: platform-admin@saas.local / password' -ForegroundColor Green
