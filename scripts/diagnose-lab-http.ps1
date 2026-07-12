# Диагностика HTTP 503 на saas.local (OSPanel + Laravel lab).
#
# 503 при корректном .env почти всегда = Apache не может достучаться до PHP-FPM
# или vhost saas.local не привязан к каталогу проекта.
#
# Usage:
#   pwsh -File scripts/diagnose-lab-http.ps1
#   pwsh -File scripts/diagnose-lab-http.ps1 -HostName saas.local

param(
    [string]$HostName = 'saas.local',
    [int]$ArtisanPort = 8765
)

$ErrorActionPreference = 'Continue'
$repoRoot = 'C:\OSPanel\home\saas\saas.local'
if (-not (Test-Path (Join-Path $repoRoot 'artisan'))) {
    $repoRoot = Split-Path -Parent $PSScriptRoot
}

function Write-Section {
    param([string]$Title)
    Write-Host ''
    Write-Host "=== $Title ===" -ForegroundColor Cyan
}

function Test-TcpPort {
    param([string]$TargetHost, [int]$Port)
    $tcp = New-Object System.Net.Sockets.TcpClient
    try {
        $tcp.Connect($TargetHost, $Port)
        $tcp.Close()
        return $true
    } catch {
        return $false
    }
}

function Get-HttpProbe {
    param([string]$Url)
    $curl = Get-Command curl.exe -ErrorAction SilentlyContinue
    if ($curl) {
        $code = & curl.exe -s -o NUL -w '%{http_code}' --max-time 10 $Url
        if ($code -match '^\d{3}$') {
            return @{
                Code = [int]$code
                Server = 'curl'
                PoweredBy = $null
                Body = ''
            }
        }
    }
    try {
        $r = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 10
        return @{
            Code = [int]$r.StatusCode
            Server = $r.Headers['Server']
            PoweredBy = $r.Headers['X-Powered-By']
            Body = ($r.Content.Substring(0, [Math]::Min(400, $r.Content.Length)) -replace '\s+', ' ')
        }
    } catch {
        $resp = $_.Exception.Response
        if ($resp) {
            $serverHeader = $null
            try { $serverHeader = $resp.Headers['Server'] } catch { }
            return @{
                Code = [int]$resp.StatusCode.value__
                Server = $serverHeader
                PoweredBy = $null
                Body = $_.Exception.Message
            }
        }
        return @{ Code = 0; Server = $null; PoweredBy = $null; Body = $_.Exception.Message }
    }
}

Write-Section 'Repo path'
Write-Host "  Root: $repoRoot"
Write-Host "  public/index.php: $(Test-Path (Join-Path $repoRoot 'public\index.php'))"
Write-Host "  .osp/project.ini: $(Test-Path (Join-Path $repoRoot '.osp\project.ini'))"
$nested = Join-Path $repoRoot 'saas.local\artisan'
if (Test-Path $nested) {
    Write-Host '  WARNING: nested saas.local/saas.local detected — run fix-nested-repo-path.ps1' -ForegroundColor Red
}

Write-Section 'OSPanel Apache vhost'
$apacheConf = 'C:\OSPanel\modules\Apache\conf\httpd.conf'
if (Test-Path $apacheConf) {
    $hits = Select-String -Path $apacheConf -Pattern $HostName -SimpleMatch
    if ($hits) {
        foreach ($h in $hits) { Write-Host "  $($h.Line.Trim())" }
    } else {
        Write-Host "  NOT FOUND: no Use Host_PHP line for $HostName in httpd.conf" -ForegroundColor Red
        Write-Host '  Fix: add domain saas.local in OSPanel → Domains → point to this repo folder.' -ForegroundColor Yellow
    }
} else {
    Write-Host '  httpd.conf not found — is OSPanel installed at C:\OSPanel?' -ForegroundColor Yellow
}

Write-Section 'PHP-FPM (Apache → 127.0.1.25:9000)'
$fpmOpen = Test-TcpPort -TargetHost '127.0.1.25' -Port 9000
if ($fpmOpen) {
    Write-Host '  127.0.1.25:9000 OPEN — PHP-FPM reachable' -ForegroundColor Green
} else {
    Write-Host '  127.0.1.25:9000 CLOSED — typical cause of HTTP 503' -ForegroundColor Red
    Write-Host '  Fix: OSPanel tray → Restart all (or restart PHP + Apache).' -ForegroundColor Yellow
}

Write-Section 'hosts file'
$hostsFile = Join-Path $env:WINDIR 'System32\drivers\etc\hosts'
if (Test-Path $hostsFile) {
    Select-String -Path $hostsFile -Pattern 'saas\.local' | ForEach-Object { Write-Host "  $($_.Line.Trim())" }
} else {
    Write-Host '  hosts file not found' -ForegroundColor Yellow
}

Write-Section 'System proxy (browser vs curl)'
$regPath = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Internet Settings'
$settings = Get-ItemProperty -Path $regPath -ErrorAction SilentlyContinue
$proxyEnable = [int]($settings.ProxyEnable ?? 0)
$proxyServer = [string]($settings.ProxyServer ?? '')
$proxyOverride = [string]($settings.ProxyOverride ?? '')
Write-Host "  ProxyEnable:   $proxyEnable"
Write-Host "  ProxyServer:   $proxyServer"
Write-Host "  ProxyOverride: $proxyOverride"
$directProbe = Get-HttpProbe "http://$HostName/"
$viaProxyCode = $null
if ($proxyServer -ne '') {
    $curl = Get-Command curl.exe -ErrorAction SilentlyContinue
    if ($curl) {
        $raw = & curl.exe -s -o NUL -w '%{http_code}' --max-time 5 -x $proxyServer "http://$HostName/"
        if ($raw -match '^\d{3}$') { $viaProxyCode = [int]$raw }
    }
}
Write-Host "  curl direct -> $($directProbe.Code)"
if ($null -ne $viaProxyCode) {
    Write-Host "  curl via $proxyServer -> $viaProxyCode"
}
if (($directProbe.Code -eq 200) -and ($viaProxyCode -eq 503)) {
    Write-Host '  LIKELY CAUSE: browser goes through proxy → 503; curl bypasses → 200' -ForegroundColor Red
    Write-Host '  Fix: pwsh -File scripts/fix-lab-proxy-bypass.ps1' -ForegroundColor Yellow
}

Write-Section 'Laravel CLI'
$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) {
    $ospanel = if (Test-Path 'C:\OSPanel') { 'C:\OSPanel' } else { 'C:\ospanel' }
    Get-ChildItem -Path "$ospanel\modules\php" -Directory -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match '^PHP-8\.3' } |
        Sort-Object Name -Descending |
        Select-Object -First 1 |
        ForEach-Object { $env:Path = "$($_.FullName);$env:Path" }
}
Set-Location $repoRoot
if (Test-Path 'artisan') {
    php artisan about 2>&1 | Select-String -Pattern 'Environment|URL|Maintenance|Database' | ForEach-Object { Write-Host "  $($_.Line.Trim())" }
} else {
    Write-Host '  artisan not found' -ForegroundColor Red
}

Write-Section 'HTTP probe (OSPanel)'
$urls = @(
    "http://$HostName/",
    "http://$HostName/login",
    "http://platform.$HostName/login"
)
$ospanelCodes = @()
foreach ($u in $urls) {
    $p = Get-HttpProbe $u
    $ospanelCodes += $p.Code
    $color = if ($p.Code -ge 200 -and $p.Code -lt 400) { 'Green' } elseif ($p.Code -eq 503) { 'Red' } else { 'Yellow' }
    Write-Host ("  {0} -> {1}" -f $u, $p.Code) -ForegroundColor $color
    if ($p.Server) { Write-Host "    Server: $($p.Server)" }
    if ($p.PoweredBy) { Write-Host "    X-Powered-By: $($p.PoweredBy)" }
    if ($p.Code -eq 503 -and $p.Body) { Write-Host "    Body: $($p.Body)" }
}
$ospanelOk = ($ospanelCodes | Where-Object { $_ -ge 200 -and $_ -lt 400 }).Count -eq $urls.Count

Write-Section 'HTTP probe (php artisan serve fallback)'
$artisanOk = $false
if (-not (Test-Path 'artisan')) {
    Write-Host '  skipped — no artisan'
} else {
    $serveJob = Start-Job -ScriptBlock {
        param($root, $port)
        Set-Location $root
        php artisan serve --host=127.0.0.1 --port=$port 2>$null
    } -ArgumentList $repoRoot, $ArtisanPort
    Start-Sleep -Seconds 4
    $fallbackUrl = "http://127.0.0.1:$ArtisanPort/"
    $curl = Get-Command curl.exe -ErrorAction SilentlyContinue
    if ($curl) {
        $code = & curl.exe -s -o NUL -w '%{http_code}' --max-time 10 -H "Host: $HostName" $fallbackUrl
        $p = @{ Code = if ($code -match '^\d{3}$') { [int]$code } else { 0 }; Server = 'artisan'; PoweredBy = $null; Body = '' }
    } else {
        $p = Get-HttpProbe $fallbackUrl
    }
    Stop-Job $serveJob -ErrorAction SilentlyContinue | Out-Null
    Remove-Job $serveJob -Force -ErrorAction SilentlyContinue | Out-Null
    $artisanOk = $p.Code -ge 200 -and $p.Code -lt 400

    if ($ospanelOk) {
        Write-Host "  skipped — OSPanel already returns 200" -ForegroundColor Green
    } elseif ($artisanOk) {
        Write-Host "  $fallbackUrl -> $($p.Code) — Laravel OK, fix OSPanel/Apache (see below)" -ForegroundColor Yellow
    } else {
        Write-Host "  $fallbackUrl -> $($p.Code) — Laravel itself may be broken" -ForegroundColor Red
        if ($p.Body) { Write-Host "    Body: $($p.Body)" }
    }
}

if ($ospanelOk) {
    Write-Section 'Result'
    Write-Host "  Lab is healthy. Open in browser:" -ForegroundColor Green
    Write-Host "    http://$HostName/"
    Write-Host "    http://$HostName/login"
    Write-Host "    http://platform.$HostName/login"
    Write-Host '  Login: admin@saas.local / password' -ForegroundColor Green
    if (($directProbe.Code -eq 200) -and ($viaProxyCode -eq 503)) {
        Write-Host ''
        Write-Host '  Browser may still show 503 until proxy bypass is fixed:' -ForegroundColor Yellow
        Write-Host '  pwsh -File scripts/fix-lab-proxy-bypass.ps1' -ForegroundColor Yellow
    }
} else {
    Write-Section 'Recommended fixes (503 or errors on OSPanel URLs)'
    Write-Host '  1. OSPanel tray → Restart all'
    Write-Host '  2. Domains: saas.local → C:\OSPanel\home\saas\saas.local (web root = public\)'
    Write-Host '  3. pwsh -File scripts/fix-nested-repo-path.ps1'
    Write-Host '  4. pwsh -File scripts/setup-platform-portal-host.ps1   # as Administrator'
    Write-Host '  5. pwsh -File scripts/repair-lab-after-pull.ps1 -Full'
}
