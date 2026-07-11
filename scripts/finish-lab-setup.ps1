# Довести lab после копирования/переноса: vendor, npm, migrate, build.
# Idempotent — безопасно перезапускать.
#
# Usage:
#   pwsh -File scripts/finish-lab-setup.ps1

param(
    [switch]$SkipBuild
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

    $dbRoot = Join-Path $ospanel 'modules\database'
    if (Test-Path $dbRoot) {
        Get-ChildItem -Path $dbRoot -Recurse -Filter 'mysql.exe' -ErrorAction SilentlyContinue |
            Select-Object -First 1 |
            ForEach-Object {
                $env:Path = "$(Split-Path -Parent $_.FullName);$env:Path"
            }
    }
}

function Ensure-Composer {
    $cmd = Get-Command composer -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }

    $local = Join-Path $repoRoot 'composer.phar'
    if (-not (Test-Path $local)) {
        Write-Host 'Downloading composer.phar...' -ForegroundColor Yellow
        php -r "copy('https://getcomposer.org/download/latest-stable/composer.phar', '$local');"
    }
    if (Test-Path $local) { return "php `"$local`"" }
    Write-Error 'Composer not found. Install from https://getcomposer.org/download/'
}

Add-OspanelPath

Write-Host "Repo: $repoRoot" -ForegroundColor Cyan
php -v

& (Join-Path $repoRoot 'scripts\apply-saas-lab-env.ps1') -SkipArtisanClear

$composer = Ensure-Composer
$vendorAutoload = Join-Path $repoRoot 'vendor\autoload.php'

if (-not (Test-Path $vendorAutoload)) {
    Write-Host '[1/5] composer install...' -ForegroundColor Cyan
    if ($composer -like 'php *') {
        Invoke-Expression "$composer install --no-interaction"
    } else {
        & $composer install --no-interaction
    }
    if (-not (Test-Path $vendorAutoload)) {
        Write-Error 'composer install failed — vendor/autoload.php still missing'
    }
} else {
    Write-Host '[1/5] vendor OK' -ForegroundColor Green
}

$envFile = Join-Path $repoRoot '.env'
if (-not (Test-Path $envFile)) {
    Copy-Item (Join-Path $repoRoot '.env.example') $envFile
}
$keyLine = Get-Content $envFile | Where-Object { $_ -match '^APP_KEY=' } | Select-Object -First 1
if (-not $keyLine -or $keyLine -match 'APP_KEY=\s*$') {
    Write-Host '[2/5] APP_KEY...' -ForegroundColor Cyan
    php artisan key:generate --force
} else {
    Write-Host '[2/5] APP_KEY OK' -ForegroundColor Green
}

if (-not (Test-Path (Join-Path $repoRoot 'node_modules'))) {
    Write-Host '[3/5] npm ci...' -ForegroundColor Cyan
    npm ci
} else {
    Write-Host '[3/5] node_modules OK' -ForegroundColor Green
}

Write-Host '[4/5] migrate + seed...' -ForegroundColor Cyan
& (Join-Path $repoRoot 'scripts\provision-database.ps1')
foreach ($sub in @('storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache')) {
    $p = Join-Path $repoRoot ($sub -replace '/', '\')
    if (-not (Test-Path $p)) { New-Item -ItemType Directory -Path $p -Force | Out-Null }
}
php artisan migrate --force --schema-path=database/schema/.skip-mysql-cli-load
php artisan db:seed --class=SaasDemoSeeder --force
php artisan db:seed --class=TenantDemoSeeder --force

if (-not $SkipBuild) {
    Write-Host '[5/5] npm run build...' -ForegroundColor Cyan
    npm run build
} else {
    Write-Host '[5/5] skip build' -ForegroundColor Yellow
}

& (Join-Path $repoRoot 'scripts\apply-saas-lab-env.ps1')

Write-Host ''
Write-Host 'Done. Open http://saas.local' -ForegroundColor Green
Write-Host 'Login: admin@saas.local / password' -ForegroundColor Green
