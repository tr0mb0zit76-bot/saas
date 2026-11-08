# Local helper: push current branch, then pull+deploy on traklo.pro
# Usage:
#   pwsh -File scripts/deploy-prod.ps1
#   pwsh -File scripts/deploy-prod.ps1 -Ref main
param(
    [string]$Ref = '',
    [string]$HostName = 'admtr4kl0@89.111.169.211',
    [string]$AppPath = '/var/www/www-root/data/www/traklo.pro',
    [switch]$SkipPush
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

if (-not $Ref) {
    $Ref = (git rev-parse --abbrev-ref HEAD).Trim()
}

Write-Host "==> branch: $Ref"

if (-not $SkipPush) {
    Write-Host "==> git push origin $Ref"
    git push -u origin "HEAD:refs/heads/$Ref"
}

Write-Host "==> remote deploy"
ssh -o ConnectTimeout=30 $HostName "cd '$AppPath' && bash scripts/deploy-prod.sh '$Ref'"
