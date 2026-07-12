# SaaS lab .env: APP_URL + CRM/SHOWCASE domains on saas.local (fixes 404 on /).
#
# Usage:
#   pwsh -File scripts/apply-saas-lab-env.ps1
#   pwsh -File scripts/apply-saas-lab-env.ps1 -HostName saas.local

param(
    [string]$HostName = 'saas.local',
    [switch]$SkipArtisanClear
)

$ErrorActionPreference = 'Stop'
$repoRoot = 'C:\OSPanel\home\saas\saas.local'
if (-not (Test-Path (Join-Path $repoRoot 'artisan'))) {
    $repoRoot = Split-Path -Parent $PSScriptRoot
}
Set-Location $repoRoot

$envFile = Join-Path $repoRoot '.env'
if (-not (Test-Path $envFile)) {
    Copy-Item (Join-Path $repoRoot '.env.example') $envFile
}

$appUrl = "http://$HostName"
$lines = Get-Content $envFile
$replacements = [ordered]@{
    'APP_URL'                  = $appUrl
    'CRM_DOMAIN'               = $HostName
    'SHOWCASE_DOMAIN'            = $HostName
    'SHOWCASE_MODE'              = 'traklo_pro'
    'SAAS_DEFAULT_TENANT_SLUG' = 'demo'
    'PLATFORM_DOMAIN'          = "platform.$HostName"
    'SAAS_PLATFORM_ADMIN_EMAILS' = 'admin@saas.local'
    'SAAS_TRIAL_DAYS'          = '14'
}

foreach ($key in $replacements.Keys) {
    $val = $replacements[$key]
    $idx = [array]::FindIndex($lines, [Predicate[string]] { param($l) $l -match "^$key=" })
    if ($idx -ge 0) {
        $lines[$idx] = "$key=$val"
    } else {
        $lines += "$key=$val"
    }
}

Set-Content -Path $envFile -Value ($lines -join "`n")
Write-Host "Updated .env for SaaS lab host: $HostName" -ForegroundColor Green
Write-Host "  APP_URL=$appUrl"
Write-Host "  CRM_DOMAIN=$HostName"
Write-Host "  SHOWCASE_DOMAIN=$HostName"
Write-Host "  PLATFORM_DOMAIN=platform.$HostName"
Write-Host "  SHOWCASE_MODE=traklo_pro"

if ($SkipArtisanClear) { return }

$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) {
    $ospanel = if (Test-Path 'C:\OSPanel') { 'C:\OSPanel' } else { 'C:\ospanel' }
    Get-ChildItem -Path "$ospanel\modules\php" -Directory -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match '^PHP-8\.3' } |
        Sort-Object Name -Descending |
        Select-Object -First 1 |
        ForEach-Object { $env:Path = "$($_.FullName);$env:Path" }
}

if (Test-Path (Join-Path $repoRoot 'artisan')) {
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    Write-Host 'Cleared config/route/view cache.' -ForegroundColor Green
}
