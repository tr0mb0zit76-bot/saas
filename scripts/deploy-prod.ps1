# Local helper: push current branch to GitHub, then update+deploy on traklo.pro.
#
# Preferred (after deploy key is on GitHub):
#   pwsh -File scripts/deploy-prod.ps1
#
# Until deploy key exists, uses a git bundle over SSH (same end state).
#
# Usage:
#   pwsh -File scripts/deploy-prod.ps1
#   pwsh -File scripts/deploy-prod.ps1 -Ref main
#   pwsh -File scripts/deploy-prod.ps1 -SkipPush
param(
    [string]$Ref = '',
    [string]$HostName = 'admtr4kl0@89.111.169.211',
    [string]$AppPath = '/var/www/www-root/data/www/traklo.pro',
    [switch]$SkipPush,
    [switch]$ForceBundle
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

$useBundle = $ForceBundle.IsPresent
if (-not $useBundle) {
    Write-Host "==> probe server git fetch"
    $probe = ssh -o ConnectTimeout=20 $HostName "cd '$AppPath' && git fetch --dry-run origin $Ref 2>&1"
    if ($LASTEXITCODE -ne 0) {
        Write-Host "origin fetch unavailable on server — falling back to git bundle"
        Write-Host $probe
        $useBundle = $true
    }
}

if ($useBundle) {
    $bundle = Join-Path $root 'tmp-traklo-deploy.bundle'
    if (Test-Path $bundle) { Remove-Item $bundle -Force }
    Write-Host "==> create bundle"
    git bundle create $bundle $Ref
    Write-Host "==> upload bundle"
    scp -o ConnectTimeout=60 $bundle "${HostName}:/tmp/traklo-deploy.bundle"
    if ($LASTEXITCODE -ne 0) { throw 'bundle upload failed' }

    Write-Host "==> remote fetch from bundle + deploy"
    ssh -o ConnectTimeout=60 $HostName "set -e; cd '$AppPath'; git fetch /tmp/traklo-deploy.bundle '${Ref}:refs/remotes/origin/${Ref}'; git checkout -f -B '$Ref' 'origin/$Ref'; git reset --hard 'origin/$Ref'; rm -f /tmp/traklo-deploy.bundle; SKIP_GIT=1 bash scripts/deploy-prod.sh '$Ref'"
    Remove-Item $bundle -Force -ErrorAction SilentlyContinue
} else {
    Write-Host "==> remote pull + deploy"
    ssh -o ConnectTimeout=60 $HostName "cd '$AppPath' && bash scripts/deploy-prod.sh '$Ref'"
}

if ($LASTEXITCODE -ne 0) { throw 'remote deploy failed' }
Write-Host 'DEPLOY_OK'
