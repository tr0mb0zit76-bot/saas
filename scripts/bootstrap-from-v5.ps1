# Bootstrap SaaS codebase from v5.local CRM.
# Copies application code, preserving SaaS-specific docs and .cursor config.
#
# Usage:
#   pwsh -File scripts/bootstrap-from-v5.ps1 -WhatIf    # preview only
#   pwsh -File scripts/bootstrap-from-v5.ps1            # execute
#
# Prerequisites:
#   - v5.local exists at default path or -V5Root
#   - saas.local is mostly empty (docs + .cursor already in place)

param(
    [string]$V5Root = 'C:\OSPanel\home\v5.local',
    [switch]$WhatIf
)

$ErrorActionPreference = 'Stop'

$saasRoot = Split-Path -Parent $PSScriptRoot

if (-not (Test-Path $V5Root)) {
    Write-Error "v5.local not found: $V5Root`nPass -V5Root if elsewhere."
}

# Directories to copy from v5 (application code)
$copyDirs = @(
    'app',
    'bootstrap',
    'config',
    'database',
    'lang',
    'public',
    'resources',
    'routes',
    'tests'
)

# Root files to copy
$copyFiles = @(
    'artisan',
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    'vite.config.js',
    'tailwind.config.js',
    'postcss.config.js',
    'phpunit.xml',
    '.env.example'
)

# Never overwrite (SaaS-specific)
$preservePaths = @(
    'docs',
    '.cursor',
    'scripts',
    'README.md',
    'AGENTS.md',
    '.git',
    '.gitignore',
    '.osp'
)

Write-Host "Bootstrap SaaS from: $V5Root"
Write-Host "Target: $saasRoot"
Write-Host ''

if ($WhatIf) {
    Write-Host '[WhatIf] Would copy directories:' -ForegroundColor Yellow
    $copyDirs | ForEach-Object { Write-Host "  $_/" }
    Write-Host '[WhatIf] Would copy files:' -ForegroundColor Yellow
    $copyFiles | ForEach-Object { Write-Host "  $_" }
    Write-Host ''
    Write-Host '[WhatIf] Would preserve:' -ForegroundColor Green
    $preservePaths | ForEach-Object { Write-Host "  $_" }
    Write-Host ''
    Write-Host 'Run without -WhatIf to execute.'
    exit 0
}

# Copy directories
foreach ($dir in $copyDirs) {
    $src = Join-Path $V5Root $dir
    $dest = Join-Path $saasRoot $dir
    if (-not (Test-Path $src)) {
        Write-Warning "Skip missing dir: $src"
        continue
    }
    if (Test-Path $dest) {
        Write-Host "Removing existing: $dir/"
        Remove-Item -Path $dest -Recurse -Force
    }
    Copy-Item -Path $src -Destination $dest -Recurse -Force
    Write-Host "Copied $dir/"
}

# Copy root files
foreach ($file in $copyFiles) {
    $src = Join-Path $V5Root $file
    $dest = Join-Path $saasRoot $file
    if (-not (Test-Path $src)) {
        Write-Warning "Skip missing file: $src"
        continue
    }
    Copy-Item -Path $src -Destination $dest -Force
    Write-Host "Copied $file"
}

Write-Host ''
Write-Host 'Bootstrap complete. Next steps:' -ForegroundColor Green
Write-Host '  1. composer install'
Write-Host '  2. npm ci'
Write-Host '  3. cp .env.example .env  (set APP_URL=http://saas.local)'
Write-Host '  4. php artisan key:generate'
Write-Host '  5. Create DB saas_crm, configure .env'
Write-Host '  6. php artisan migrate'
Write-Host '  7. Start Phase 1: tenants migration'
