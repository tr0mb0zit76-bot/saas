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
    [switch]$WhatIf
)

$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
$envFile = Join-Path $repoRoot '.env'

function Get-EnvValue {
    param([string]$Key)
    if (-not (Test-Path $envFile)) { return $null }
    $line = Get-Content $envFile | Where-Object { $_ -match "^$Key=" } | Select-Object -First 1
    if (-not $line) { return $null }
    return ($line -split '=', 2)[1].Trim().Trim('"').Trim("'")
}

function Find-MySqlClient {
    $candidates = @(
        'mysql',
        'C:\OSPanel\modules\database\MySQL-8.0\bin\mysql.exe',
        'C:\OSPanel\modules\database\MySQL-8.4\bin\mysql.exe',
        'C:\OSPanel\modules\database\MySQL-5.7\bin\mysql.exe'
    )
    foreach ($c in $candidates) {
        if ($c -eq 'mysql') {
            $cmd = Get-Command mysql -ErrorAction SilentlyContinue
            if ($cmd) { return $cmd.Source }
        } elseif (Test-Path $c) {
            return $c
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

$mysql = Find-MySqlClient
if (-not $mysql) {
    Write-Error "mysql client not found. Add OSPanel MySQL bin to PATH or install mysql CLI."
}

$sql = "CREATE DATABASE IF NOT EXISTS ``$DatabaseName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

Write-Host "Target database: $DatabaseName"
Write-Host "mysql client: $mysql"

if ($WhatIf) {
    Write-Host "[WhatIf] Would try hosts: $($HostCandidates -join ', ')" -ForegroundColor Yellow
    Write-Host "[WhatIf] SQL: $sql"
    exit 0
}

$connected = $false
$workingHost = $null

foreach ($h in $HostCandidates) {
    Write-Host "Trying host $h ..."
    $args = @('-h', $h, '-u', $User, '-e', $sql)
    if ($Password -ne '') {
        $args = @('-h', $h, '-u', $User, "-p$Password", '-e', $sql)
    }
    try {
        & $mysql @args 2>$null
        if ($LASTEXITCODE -eq 0) {
            $connected = $true
            $workingHost = $h
            Write-Host "Database ready on $h" -ForegroundColor Green
            break
        }
    } catch {
        # try next host
    }
}

if (-not $connected -and $Password -eq '') {
    Write-Host 'Retry with empty password failed; trying without explicit user password flag...'
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

if (-not $connected) {
    Write-Error @"
Could not create database '$DatabaseName'.
Tried hosts: $($HostCandidates -join ', ')
Escalate to human: confirm OSPanel MySQL is running and provide DB_PASSWORD in .env
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
