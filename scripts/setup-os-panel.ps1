# OSPanel local setup — run once after git pull on Windows.
#
#   cd C:\OSPanel\home\saas\saas.local
#   pwsh -File scripts/setup-os-panel.ps1
#
# Requires: v5.local at C:\OSPanel\home\v5.local, OSPanel domain saas.local, PHP 8.3, MySQL.

param(
    [string]$V5Root = 'C:\OSPanel\home\v5.local',
    [switch]$SkipBootstrap
)

$ErrorActionPreference = 'Stop'

$canonicalRoot = 'C:\OSPanel\home\saas\saas.local'
$fixScript = Join-Path $PSScriptRoot 'fix-nested-repo-path.ps1'
if (Test-Path $fixScript) {
    & pwsh -NoProfile -File $fixScript | Out-Null
}

if (Test-Path (Join-Path $canonicalRoot 'artisan')) {
    $repoRoot = $canonicalRoot
} else {
    $repoRoot = Split-Path -Parent $PSScriptRoot
}
Set-Location $repoRoot

Write-Host '=== SaaS OSPanel Local Setup ===' -ForegroundColor Cyan
Write-Host "Repo: $repoRoot"
Write-Host "v5 source: $V5Root"
Write-Host ''

# Ensure git has latest code (main with full bootstrap)
$branch = git rev-parse --abbrev-ref HEAD 2>$null
Write-Host "Git branch: $branch"
git pull origin main 2>$null
if ($LASTEXITCODE -ne 0) {
    git pull origin cursor/migration-orchestrator-4010 2>$null
}

# Add OSPanel PHP/MySQL to PATH for this session
$ospanel = 'C:\OSPanel'
if (-not (Test-Path $ospanel)) {
    $ospanel = 'C:\ospanel'
}

$phpDirs = Get-ChildItem -Path "$ospanel\modules\php" -Directory -ErrorAction SilentlyContinue | Sort-Object Name -Descending
foreach ($phpDir in $phpDirs) {
    if ($phpDir.Name -match '^PHP-8\.3') {
        $env:Path = "$($phpDir.FullName);$env:Path"
        break
    }
}

$dbRoot = Join-Path $ospanel 'modules\database'
if (Test-Path $dbRoot) {
    $mysqlExe = Get-ChildItem -Path $dbRoot -Recurse -Filter 'mysql.exe' -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($mysqlExe) {
        $binDir = Split-Path -Parent $mysqlExe.FullName
        $env:Path = "$binDir;$env:Path"
        Write-Host "MySQL CLI: $($mysqlExe.FullName)"
    } else {
        Write-Host 'mysql.exe not in OSPanel — provision-database.ps1 will use PHP fallback' -ForegroundColor Yellow
    }
}

$params = @{}
if ($SkipBootstrap) { $params['SkipBootstrap'] = $true }
$params['V5Root'] = $V5Root

& (Join-Path $repoRoot 'scripts\setup-lab.ps1') @params

Write-Host ''
Write-Host 'If http://saas.local fails, restart Apache in OSPanel.' -ForegroundColor Yellow
