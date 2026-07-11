# Create MySQL database for SaaS lab (OSPanel-friendly).
# Idempotent: safe to re-run.
#
# Usage:
#   pwsh -File scripts/provision-database.ps1
#   pwsh -File scripts/provision-database.ps1 -WhatIf
#   pwsh -File scripts/provision-database.ps1 -DatabaseName saas_crm -Host 127.0.1.21

param(
    [string]$DatabaseName = 'saas_crm',
    [string[]]$HostCandidates = @('127.0.1.21', '127.0.0.1', 'localhost'),
    [string]$User = 'root',
    [string]$Password = '',
    [string]$OspanelRoot = 'C:\OSPanel',
    [switch]$WhatIf
)

$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
$envFile = Join-Path $repoRoot '.env'
$phpFallback = Join-Path $repoRoot 'scripts\provision-database.php'

function Get-EnvValue {
    param([string]$Key)
    if (-not (Test-Path $envFile)) { return $null }
    $line = Get-Content $envFile | Where-Object { $_ -match "^$Key=" } | Select-Object -First 1
    if (-not $line) { return $null }
    return ($line -split '=', 2)[1].Trim().Trim('"').Trim("'")
}

function Find-MySqlClient {
    param([string]$OspanelRoot)

    $cmd = Get-Command mysql -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }

    $searchRoots = @(
        (Join-Path $OspanelRoot 'modules\database'),
        (Join-Path $OspanelRoot 'modules\Database'),
        'C:\OSPanel\modules\database',
        'C:\OSPanel\modules\Database'
    ) | Select-Object -Unique

    foreach ($root in $searchRoots) {
        if (-not (Test-Path $root)) { continue }

        $exe = Get-ChildItem -Path $root -Recurse -Filter 'mysql.exe' -ErrorAction SilentlyContinue |
            Select-Object -First 1 -ExpandProperty FullName

        if ($exe) { return $exe }
    }

    return $null
}

function Invoke-PhpDatabaseProvision {
    param(
        [string]$PhpFallback,
        [string[]]$HostCandidates,
        [string]$User,
        [string]$Password,
        [string]$DatabaseName
    )

    if (-not (Test-Path $PhpFallback)) {
        return $null
    }

    $php = Get-Command php -ErrorAction SilentlyContinue
    if (-not $php) { return $null }

    Write-Host 'mysql.exe not found — using PHP PDO fallback...' -ForegroundColor Yellow

    foreach ($h in $HostCandidates) {
        $output = & $php.Source $PhpFallback $h $User $Password $DatabaseName 2>&1
        if ($LASTEXITCODE -eq 0 -and ($output -match '^OK:(.+)$')) {
            return $Matches[1]
        }
    }

    return $null
}

# Read credentials from .env if present
if (Test-Path $envFile) {
    $envDb = Get-EnvValue 'DB_DATABASE'
    if ($envDb) { $DatabaseName = $envDb }
    $envHost = Get-EnvValue 'DB_HOST'
    if ($envHost) { $HostCandidates = @($envHost) + $HostCandidates | Select-Object -Unique }
    $envUser = Get-EnvValue 'DB_USERNAME'
    if ($envUser) { $User = $envUser }
    $envPass = Get-EnvValue 'DB_PASSWORD'
    if ($null -ne $envPass) { $Password = $envPass }
}

$sql = "CREATE DATABASE IF NOT EXISTS ``$DatabaseName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

Write-Host "Target database: $DatabaseName"

if ($WhatIf) {
    Write-Host "[WhatIf] Would try hosts: $($HostCandidates -join ', ')" -ForegroundColor Yellow
    Write-Host "[WhatIf] SQL: $sql"
    $mysql = Find-MySqlClient -OspanelRoot $OspanelRoot
    Write-Host "[WhatIf] mysql client: $(if ($mysql) { $mysql } else { 'not found — would use PHP fallback' })"
    exit 0
}

$mysql = Find-MySqlClient -OspanelRoot $OspanelRoot
$connected = $false
$workingHost = $null

if ($mysql) {
    Write-Host "mysql client: $mysql"

    foreach ($h in $HostCandidates) {
        Write-Host "Trying host $h ..."
        $argList = @('-h', $h, '-u', $User, '-e', $sql)
        if ($Password -ne '') {
            $argList = @('-h', $h, '-u', $User, "-p$Password", '-e', $sql)
        }
        try {
            & $mysql @argList 2>$null
            if ($LASTEXITCODE -eq 0) {
                $connected = $true
                $workingHost = $h
                Write-Host "Database ready on $h" -ForegroundColor Green
                break
            }
        } catch { }
    }

    if (-not $connected -and $Password -eq '') {
        foreach ($h in $HostCandidates) {
            try {
                & $mysql -h $h -u $User --password= -e $sql 2>$null
                if ($LASTEXITCODE -eq 0) {
                    $connected = $true
                    $workingHost = $h
                    Write-Host "Database ready on $h (empty password)" -ForegroundColor Green
                    break
                }
            } catch { }
        }
    }
}

if (-not $connected) {
    $workingHost = Invoke-PhpDatabaseProvision -PhpFallback $phpFallback `
        -HostCandidates $HostCandidates -User $User -Password $Password -DatabaseName $DatabaseName
    if ($workingHost) {
        $connected = $true
        Write-Host "Database ready on $workingHost (PHP)" -ForegroundColor Green
    }
}

if (-not $connected) {
    Write-Error @"
Could not create database '$DatabaseName'.
Tried hosts: $($HostCandidates -join ', ')
Confirm OSPanel MySQL is running. If root has a password, set DB_PASSWORD in .env
"@
}

# Patch .env if exists
if (Test-Path $envFile) {
    $content = Get-Content $envFile -Raw
    $updates = @{
        'DB_CONNECTION' = 'mysql'
        'DB_HOST'       = $workingHost
        'DB_PORT'       = '3306'
        'DB_DATABASE'   = $DatabaseName
        'DB_USERNAME'   = $User
    }
    foreach ($key in $updates.Keys) {
        $val = $updates[$key]
        if ($content -match "(?m)^$key=") {
            $content = $content -replace "(?m)^$key=.*", "$key=$val"
        } else {
            $content += "`n$key=$val"
        }
    }
    if ($Password -ne '' -and $content -notmatch '(?m)^DB_PASSWORD=') {
        $content += "`nDB_PASSWORD=$Password"
    }
    Set-Content -Path $envFile -Value $content.TrimEnd() -NoNewline
    Write-Host "Updated .env DB_* for host $workingHost"
}

Write-Host "Done. Database '$DatabaseName' is ready."
