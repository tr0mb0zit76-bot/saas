# OSPanel: platform.saas.local → тот же vhost, что saas.local (server_aliases в .osp/project.ini).
# Запуск от администратора, если hosts не обновился через OSPanel:
#   pwsh -File scripts/setup-platform-portal-host.ps1
#
# После скрипта: перезапустите Apache в OSPanel (или весь OSPanel).

param(
    [string]$PlatformHost = 'platform.saas.local',
    [string]$BindIp = '127.0.1.11',
    [switch]$SkipHosts,
    [switch]$SkipApachePatch
)

$ErrorActionPreference = 'Stop'
$repoRoot = if (Test-Path (Join-Path $PSScriptRoot '..\artisan')) {
    (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
} else {
    'C:\OSPanel\home\saas\saas.local'
}
Set-Location $repoRoot

$hostsFile = Join-Path $env:WINDIR 'System32\drivers\etc\hosts'
$apacheConf = 'C:\OSPanel\modules\Apache\conf\httpd.conf'
$projectIni = Join-Path $repoRoot '.osp\project.ini'
$envFile = Join-Path $repoRoot '.env'

function Set-EnvKey {
    param([string]$Key, [string]$Value)
    $lines = Get-Content $envFile
    $idx = [array]::FindIndex($lines, [Predicate[string]] { param($l) $l -match "^$Key=" })
    if ($idx -ge 0) {
        $lines[$idx] = "$Key=$Value"
    } else {
        $lines += "$Key=$Value"
    }
    Set-Content -Path $envFile -Value ($lines -join "`n")
}

Write-Host "Traklo Pro platform portal host setup" -ForegroundColor Cyan
Write-Host "  Platform: $PlatformHost"
Write-Host "  Bind IP:  $BindIp (same as saas.local in OSPanel)"

if (-not (Test-Path $envFile)) {
    Copy-Item (Join-Path $repoRoot '.env.example') $envFile
}

Set-EnvKey 'PLATFORM_DOMAIN' $PlatformHost
Set-EnvKey 'SAAS_PLATFORM_ADMIN_EMAILS' 'admin@saas.local'
Set-EnvKey 'SAAS_TRIAL_DAYS' '14'
Write-Host "Updated .env (PLATFORM_DOMAIN, SAAS_*)" -ForegroundColor Green

if (Test-Path $projectIni) {
    $ini = Get-Content $projectIni -Raw
    if ($ini -notmatch 'server_aliases\s*=') {
        $ini = $ini.TrimEnd() + "`nserver_aliases = www.saas.local $PlatformHost`n"
    } elseif ($ini -notmatch [regex]::Escape($PlatformHost)) {
        $ini = $ini -replace '(server_aliases\s*=\s*)(.*)', {
            $prefix = $args[0].Groups[1].Value
            $aliases = $args[0].Groups[2].Value.Trim()
            if ($aliases -eq '') { "$prefix www.saas.local $PlatformHost" }
            else { "$prefix $aliases $PlatformHost" }
        }
    }
    Set-Content -Path $projectIni -Value $ini.TrimEnd() -NoNewline
    Add-Content -Path $projectIni -Value "`n"
    Write-Host "Updated .osp/project.ini server_aliases" -ForegroundColor Green
}

if (-not $SkipHosts -and (Test-Path $hostsFile)) {
    $hosts = Get-Content $hostsFile -Raw
    if ($hosts -notmatch [regex]::Escape($PlatformHost)) {
        $entry = "`n$BindIp $PlatformHost"
        try {
            Add-Content -Path $hostsFile -Value $entry -ErrorAction Stop
            Write-Host "Added hosts entry: $BindIp $PlatformHost" -ForegroundColor Green
        } catch {
            Write-Host "Could not write hosts file (run PowerShell as Administrator):" -ForegroundColor Yellow
            Write-Host "  $BindIp $PlatformHost"
        }
    } else {
        Write-Host "Hosts entry already present for $PlatformHost" -ForegroundColor Green
    }
}

if (-not $SkipApachePatch -and (Test-Path $apacheConf)) {
    $lines = Get-Content $apacheConf
    $changed = $false
    for ($i = 0; $i -lt $lines.Count; $i++) {
        if ($lines[$i] -match '^Use Host_PHP saas\.local "127\.0\.1\.11:80" "([^"]+)"') {
            $aliases = $Matches[1]
            if ($aliases -notmatch [regex]::Escape($PlatformHost)) {
                $newAliases = "$aliases $PlatformHost".Trim()
                $lines[$i] = $lines[$i] -replace '"[^"]+" "C:/OSPanel/home/saas/saas.local"', "`"$newAliases`" `"C:/OSPanel/home/saas/saas.local`""
                $changed = $true
            }
        }
        if ($lines[$i] -match '^Use Host_PHP_SSL saas\.local "127\.0\.1\.11:443" "([^"]+)"') {
            $aliases = $Matches[1]
            if ($aliases -notmatch [regex]::Escape($PlatformHost)) {
                $newAliases = "$aliases $PlatformHost".Trim()
                $lines[$i] = $lines[$i] -replace '"127\.0\.1\.11:443" "[^"]+"', "`"127.0.1.11:443`" `"$newAliases`""
                $changed = $true
            }
        }
    }
    if ($changed) {
        try {
            Set-Content -Path $apacheConf -Value $lines -ErrorAction Stop
            Write-Host 'Patched Apache httpd.conf ServerAlias' -ForegroundColor Green
        } catch {
            Write-Host 'Could not patch httpd.conf — restart Apache from OSPanel after saving project.ini' -ForegroundColor Yellow
        }
    } else {
        Write-Host "Apache httpd.conf already includes $PlatformHost" -ForegroundColor Green
    }
}

$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) {
    $ospanel = if (Test-Path 'C:\OSPanel') { 'C:\OSPanel' } else { 'C:\ospanel' }
    Get-ChildItem -Path "$ospanel\modules\php" -Directory -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match '^PHP-8\.3' } |
        Sort-Object Name -Descending |
        Select-Object -First 1 |
        ForEach-Object { $env:Path = "$($_.FullName);$env:Path" }
}

if (Test-Path (Join-Path $repoRoot 'artisan')) {
    php artisan config:clear | Out-Null
    php artisan route:clear | Out-Null
    Write-Host 'Cleared Laravel config/route cache.' -ForegroundColor Green
}

Write-Host ''
Write-Host "Open: http://$PlatformHost/login" -ForegroundColor Green
Write-Host 'Login: admin@saas.local / password' -ForegroundColor Green
Write-Host 'If still ERR_NAME_NOT_RESOLVED: run this script as Administrator, then restart Apache in OSPanel.' -ForegroundColor Yellow
