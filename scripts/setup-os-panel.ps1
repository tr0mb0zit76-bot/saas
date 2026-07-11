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
$repoRoot = Split-Path -Parent $PSScriptRoot
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
$phpDirs = Get-ChildItem -Path "$ospanel\modules\php" -Directory -ErrorAction SilentlyContinue | Sort-Object Name -Descending
foreach ($phpDir in $phpDirs) {
    if ($phpDir.Name -match '^PHP-8\.3') {
        $env:Path = "$($phpDir.FullName);$env:Path"
        break
    }
}
$mysqlDirs = Get-ChildItem -Path "$ospanel\modules\database" -Directory -ErrorAction SilentlyContinue | Sort-Object Name -Descending
foreach ($mysqlDir in $mysqlDirs) {
    if ($mysqlDir.Name -match '^MySQL') {
        $env:Path = "$($mysqlDir.FullName)\bin;$env:Path"
        break
    }
}

$params = @{}
if ($SkipBootstrap) { $params['SkipBootstrap'] = $true }
$params['V5Root'] = $V5Root

& (Join-Path $repoRoot 'scripts\setup-lab.ps1') @params

Write-Host ''
Write-Host 'If http://saas.local fails, restart Apache in OSPanel.' -ForegroundColor Yellow
