# Report migration progress from migration-state.json + filesystem probes.
#
# Usage:
#   pwsh -File scripts/migration-status.ps1

$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
$stateFile = Join-Path $repoRoot 'docs\sync\migration-state.json'

Write-Host '=== SaaS Migration Status ===' -ForegroundColor Cyan
Write-Host "Repo: $repoRoot"
Write-Host ''

# Load state
if (Test-Path $stateFile) {
    $state = Get-Content $stateFile -Raw | ConvertFrom-Json
    Write-Host "Phase: $($state.current_phase)" -ForegroundColor Yellow
    Write-Host "Updated: $($state.updated_at)"
    if ($state.blockers.Count -gt 0) {
        Write-Host 'BLOCKERS:' -ForegroundColor Red
        $state.blockers | ForEach-Object { Write-Host "  - $_" }
    }
    Write-Host ''
    $pending = $state.steps.PSObject.Properties | Where-Object { $_.Value.status -ne 'done' }
    $done = $state.steps.PSObject.Properties | Where-Object { $_.Value.status -eq 'done' }
    Write-Host "Steps done: $($done.Count) / $($state.steps.PSObject.Properties.Count)"
    $next = $pending | Select-Object -First 3
    if ($next) {
        Write-Host 'Next steps:'
        $next | ForEach-Object { Write-Host "  $($_.Name): $($_.Value.note) [$($_.Value.status)]" }
    }
} else {
    Write-Warning "State file not found: $stateFile"
}

Write-Host ''
Write-Host '--- Filesystem probes ---' -ForegroundColor Cyan

$probes = @{
    'Laravel (artisan)'  = (Test-Path (Join-Path $repoRoot 'artisan'))
    'vendor/'            = (Test-Path (Join-Path $repoRoot 'vendor'))
    'node_modules/'      = (Test-Path (Join-Path $repoRoot 'node_modules'))
    '.env'               = (Test-Path (Join-Path $repoRoot '.env'))
    'public/build/'      = (Test-Path (Join-Path $repoRoot 'public\build'))
}

foreach ($k in $probes.Keys) {
    $icon = if ($probes[$k]) { '[OK]' } else { '[--]' }
    Write-Host "  $icon $k"
}

if (Test-Path (Join-Path $repoRoot 'artisan')) {
    try {
        Push-Location $repoRoot
        $about = php artisan about --only=environment 2>$null
        if ($about) { Write-Host ''; Write-Host $about }
    } catch {
        Write-Host '  [--] php artisan about (failed — check .env / PHP)'
    } finally {
        Pop-Location
    }
}

Write-Host ''
Write-Host 'Orchestrator: saas-migration-orchestrator'
Write-Host 'Runbook: docs/sync/migration-runbook.md'
