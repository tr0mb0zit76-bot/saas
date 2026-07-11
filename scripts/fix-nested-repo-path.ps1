# Fix accidental double nesting: ...\saas.local\saas.local -> ...\saas.local
# Idempotent — safe to re-run.
#
# Usage:
#   pwsh -File scripts/fix-nested-repo-path.ps1
#   pwsh -File scripts/fix-nested-repo-path.ps1 -WhatIf

param(
    [string]$TargetRoot = 'C:\OSPanel\home\saas\saas.local',
    [switch]$WhatIf
)

$ErrorActionPreference = 'Stop'

function Test-RepoRoot {
    param([string]$Path)
    return (Test-Path (Join-Path $Path 'artisan')) -and (Test-Path (Join-Path $Path '.git'))
}

# If invoked from nested checkout, derive TargetRoot automatically.
$scriptRepo = Split-Path -Parent $PSScriptRoot
if ((Split-Path -Leaf $scriptRepo) -eq 'saas.local') {
    $parent = Split-Path -Parent $scriptRepo
    if ((Split-Path -Leaf $parent) -eq 'saas.local') {
        $TargetRoot = $parent
    }
}

$nested = Join-Path $TargetRoot 'saas.local'

if (-not (Test-RepoRoot $nested)) {
    if (Test-RepoRoot $TargetRoot) {
        Write-Host "Repo path OK: $TargetRoot" -ForegroundColor Green
        return $TargetRoot
    }
    Write-Error "Neither '$TargetRoot' nor '$nested' looks like a Laravel git repo (artisan + .git)."
}

Write-Host "Nested repo detected:" -ForegroundColor Yellow
Write-Host "  wrong: $nested"
Write-Host "  right: $TargetRoot"

if ($WhatIf) {
    Write-Host '[WhatIf] Would move nested folder up one level.' -ForegroundColor Yellow
    return $TargetRoot
}

$parentDir = Split-Path -Parent $TargetRoot
$tmp = Join-Path $parentDir ('_saas_reloc_' + [guid]::NewGuid().ToString('N').Substring(0, 8))

Write-Host "Relocating via temp: $tmp"

Move-Item -Path $nested -Destination $tmp

if (Test-Path $TargetRoot) {
    $outerHasRepo = Test-RepoRoot $TargetRoot
    if ($outerHasRepo) {
        Write-Error "Outer folder '$TargetRoot' also contains a repo. Resolve manually before re-run."
    }
    Remove-Item -Path $TargetRoot -Recurse -Force
}

Move-Item -Path $tmp -Destination $TargetRoot

if (-not (Test-RepoRoot $TargetRoot)) {
    Write-Error "Relocation finished but '$TargetRoot' is not a valid repo root."
}

Write-Host "Done. Repo root: $TargetRoot" -ForegroundColor Green
Write-Host 'Update OSPanel web_root to: ' -NoNewline
Write-Host (Join-Path $TargetRoot 'public') -ForegroundColor Cyan

return $TargetRoot
