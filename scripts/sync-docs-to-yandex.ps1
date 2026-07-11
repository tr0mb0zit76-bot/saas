# Копирует документацию из docs/sync/ и docs/architecture/ в Obsidian vault на Yandex Disk.
#
# Usage:
#   pwsh -File scripts/sync-docs-to-yandex.ps1
#   pwsh -File scripts/sync-docs-to-yandex.ps1 -ExchangeRoot "D:\YandexDisk\Exchange"

param(
    [string]$ExchangeRoot = 'C:\Sync\Yandex.Disk\Exchange'
)

$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$syncDir = Join-Path $repoRoot 'docs\sync'
$archDir = Join-Path $repoRoot 'docs\architecture'
$saasRoot = Join-Path $ExchangeRoot 'saas'
$archDest = Join-Path $saasRoot 'architecture'
$phasesDest = Join-Path $archDest 'phases'
$decisionsDest = Join-Path $archDest 'decisions'

if (-not (Test-Path $ExchangeRoot)) {
    Write-Error "Yandex Disk Exchange not found: $ExchangeRoot`nPass -ExchangeRoot if vault is elsewhere."
}

# Ensure directories
@($saasRoot, $archDest, $phasesDest, $decisionsDest) | ForEach-Object {
    if (-not (Test-Path $_)) {
        New-Item -ItemType Directory -Path $_ -Force | Out-Null
    }
}

# Root sync files
$rootMap = @{
    '00-index.md'              = (Join-Path $saasRoot '00-index.md')
    'Cursor-handoff-latest.md' = (Join-Path $saasRoot 'Cursor-handoff-latest.md')
    'cursor-agent-startup.md'  = (Join-Path $saasRoot 'cursor-agent-startup.md')
    'architecture-plan.md'     = (Join-Path $archDest 'plan.md')
}

foreach ($srcName in $rootMap.Keys) {
    $src = Join-Path $syncDir $srcName
    if (-not (Test-Path $src)) {
        Write-Warning "Skip missing: $src"
        continue
    }
    Copy-Item -Path $src -Destination $rootMap[$srcName] -Force
    Write-Host "Synced $srcName -> $($rootMap[$srcName])"
}

# Architecture files (recursive)
if (Test-Path $archDir) {
    Get-ChildItem -Path $archDir -Recurse -File | ForEach-Object {
        $relativePath = $_.FullName.Substring($archDir.Length + 1)
        $dest = Join-Path $archDest $relativePath
        $parent = Split-Path -Parent $dest
        if (-not (Test-Path $parent)) {
            New-Item -ItemType Directory -Path $parent -Force | Out-Null
        }
        Copy-Item -Path $_.FullName -Destination $dest -Force
        Write-Host "Synced architecture/$relativePath"
    }
}

Write-Host ''
Write-Host 'Done. Open Exchange/saas/Cursor-handoff-latest.md in Obsidian or @-mention in Cursor.'
