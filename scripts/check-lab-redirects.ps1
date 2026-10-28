# Detect redirect loops and verify lab pages return 200 (not 302→self).
# Usage: pwsh -File scripts/check-lab-redirects.ps1
#        pwsh -File scripts/check-lab-redirects.ps1 -HostName saas.local

param(
    [string]$HostName = 'saas.local'
)

$ErrorActionPreference = 'Stop'
$repoRoot = 'C:\OSPanel\home\saas\saas.local'
if (-not (Test-Path (Join-Path $repoRoot 'artisan'))) {
    $repoRoot = Split-Path -Parent $PSScriptRoot
}

function Get-FirstRedirect {
    param([string]$Url)
    $out = & curl.exe -sI --max-time 10 $Url 2>&1
    $status = 0
    $location = $null
    foreach ($line in ($out -split "`n")) {
        if ($line -match '^HTTP/\S+\s+(\d+)') { $status = [int]$Matches[1] }
        if ($line -match '^Location:\s*(.+)\s*$') { $location = $Matches[1].Trim() }
    }
    return @{ Status = $status; Location = $location }
}

function Test-RedirectLoop {
    param([string]$StartUrl)
    $seen = [System.Collections.Generic.HashSet[string]]::new([StringComparer]::OrdinalIgnoreCase)
    $url = $StartUrl
    $hops = 0
    while ($hops -lt 12) {
        if (-not $seen.Add($url)) {
            return @{ Loop = $true; Url = $url; Hops = $hops }
        }
        $r = Get-FirstRedirect -Url $url
        if ($r.Status -ge 200 -and $r.Status -lt 300) {
            return @{ Loop = $false; Final = $r.Status; Url = $url; Hops = $hops }
        }
        if ($r.Status -lt 300 -or $r.Status -ge 400 -or [string]::IsNullOrWhiteSpace($r.Location)) {
            return @{ Loop = $false; Final = $r.Status; Url = $url; Hops = $hops; Location = $r.Location }
        }
        $url = $r.Location
        $hops++
    }
    return @{ Loop = $true; Url = $url; Hops = $hops; Reason = 'max hops' }
}

$paths = @('/', '/login', '/dashboard')
$base = "http://$HostName"
$failed = $false

Write-Host "=== Lab redirect check ($base) ===" -ForegroundColor Cyan

foreach ($path in $paths) {
    $start = "$base$path"
    $result = Test-RedirectLoop -StartUrl $start
    if ($result.Loop) {
        Write-Host "  FAIL $path -> redirect loop at $($result.Url) ($($result.Hops) hops)" -ForegroundColor Red
        $failed = $true
    } elseif ($result.Final -ge 200 -and $result.Final -lt 400) {
        Write-Host "  OK   $path -> $($result.Final) ($($result.Hops) redirect(s))" -ForegroundColor Green
    } else {
        Write-Host "  WARN $path -> HTTP $($result.Final) (expected 2xx)" -ForegroundColor Yellow
        if ($result.Final -ge 300 -and $result.Final -lt 400) {
            $failed = $true
        }
    }
}

if ($failed) {
    Write-Host ''
    Write-Host 'Fix: pwsh -File scripts/repair-lab-after-pull.ps1 -Full' -ForegroundColor Yellow
    exit 1
}

Write-Host ''
Write-Host 'Redirect check OK' -ForegroundColor Green
exit 0
