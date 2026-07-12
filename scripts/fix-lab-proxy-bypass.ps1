# Lab-домены (saas.local) часто ломаются из-за системного прокси (Clash/V2Ray на 127.0.0.1:10809):
#   curl  → напрямую на OSPanel → 200
#   Chrome/Edge → через прокси → 503
#
# Usage:
#   pwsh -File scripts/fix-lab-proxy-bypass.ps1
#   pwsh -File scripts/fix-lab-proxy-bypass.ps1 -HostName saas.local

param(
    [string]$HostName = 'saas.local',
    [switch]$WhatIf
)

$ErrorActionPreference = 'Stop'
$platformHost = "platform.$HostName"
$regPath = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Internet Settings'
$directUrl = "http://$HostName/"
$proxyTestUrl = $directUrl

function Test-CurlStatus {
    param(
        [string]$Url,
        [string]$Proxy = ''
    )
    $curl = Get-Command curl.exe -ErrorAction SilentlyContinue
    if (-not $curl) { return $null }
    if ($Proxy -ne '') {
        $code = & curl.exe -s -o NUL -w '%{http_code}' --max-time 5 -x $Proxy $Url
    } else {
        $code = & curl.exe -s -o NUL -w '%{http_code}' --max-time 5 $Url
    }
    if ($code -match '^\d{3}$') { return [int]$code }
    return $null
}

Write-Host '=== Lab proxy bypass ===' -ForegroundColor Cyan

$settings = Get-ItemProperty -Path $regPath -ErrorAction SilentlyContinue
$proxyEnable = [int]($settings.ProxyEnable ?? 0)
$proxyServer = [string]($settings.ProxyServer ?? '')
$proxyOverride = [string]($settings.ProxyOverride ?? '')

Write-Host "  ProxyEnable:   $proxyEnable"
Write-Host "  ProxyServer:   $proxyServer"
Write-Host "  ProxyOverride: $proxyOverride"

$directCode = Test-CurlStatus -Url $directUrl
$viaProxyCode = $null
if ($proxyServer -ne '') {
    $viaProxyCode = Test-CurlStatus -Url $directUrl -Proxy $proxyServer
}

Write-Host ''
Write-Host "  curl direct:       $directUrl -> $directCode"
if ($null -ne $viaProxyCode) {
    Write-Host "  curl via proxy:    $directUrl -> $viaProxyCode (proxy $proxyServer)"
}

$needsBypass = ($directCode -eq 200) -and ($viaProxyCode -eq 503)
if (-not $needsBypass -and $proxyEnable -eq 0) {
    Write-Host ''
    Write-Host 'System proxy is off — 503 in browser has another cause. Run diagnose-lab-http.ps1' -ForegroundColor Yellow
    return
}

$bypassHosts = @(
    $HostName,
    "www.$HostName",
    $platformHost,
    "*.$HostName"
)

$missing = @()
foreach ($h in $bypassHosts) {
    if ($proxyOverride -notmatch [regex]::Escape($h)) {
        $missing += $h
    }
}

if ($missing.Count -eq 0 -and -not $needsBypass) {
    Write-Host ''
    Write-Host 'Bypass entries already present. If browser still 503:' -ForegroundColor Yellow
    Write-Host '  1. Fully quit Chrome/Edge (all windows) and reopen'
    Write-Host '  2. In Clash/V2Ray add rule: DOMAIN-SUFFIX,saas.local,DIRECT'
    Write-Host "  3. Open: $directUrl"
    return
}

if ($missing.Count -gt 0) {
    $newOverride = ($proxyOverride.Trim().TrimEnd(';') + ';' + ($missing -join ';')).Trim(';')
    Write-Host ''
    Write-Host 'Adding to ProxyOverride:' -ForegroundColor Yellow
    foreach ($h in $missing) { Write-Host "  + $h" }

    if ($WhatIf) {
        Write-Host "[WhatIf] Would set ProxyOverride=$newOverride" -ForegroundColor Yellow
    } else {
        Set-ItemProperty -Path $regPath -Name ProxyOverride -Value $newOverride
        Write-Host 'Updated ProxyOverride in registry.' -ForegroundColor Green
    }
}

Write-Host ''
Write-Host 'Next steps:' -ForegroundColor Cyan
Write-Host '  1. Close ALL browser windows (Chrome/Edge/Cursor browser)'
Write-Host '  2. If using Clash/V2Ray — add DIRECT rule for *.saas.local or turn TUN off for lab'
Write-Host "  3. Open in Chrome: $directUrl"
Write-Host "  4. Login: http://$HostName/login  (admin@saas.local / password)"

if ($needsBypass) {
    Write-Host ''
    Write-Host 'Root cause: browser uses proxy, curl does not → false 503.' -ForegroundColor Green
}
