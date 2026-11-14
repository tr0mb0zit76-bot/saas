# OSPanel MySQL helpers — bind_address and mysql.exe discovery (Windows lab).
# Dot-source from other scripts: . (Join-Path $PSScriptRoot 'ospanel-mysql.ps1')

function Get-OspanelRoot {
    param([string]$Preferred = 'C:\OSPanel')
    if (Test-Path $Preferred) { return $Preferred }
    if (Test-Path 'C:\ospanel') { return 'C:\ospanel' }
    return $Preferred
}

function Find-OspanelMySqlClient {
    param([string]$OspanelRoot = (Get-OspanelRoot))

    $cmd = Get-Command mysql -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }

    $patterns = @(
        (Join-Path $OspanelRoot 'modules\MySQL-*\bin\mysql.exe'),
        (Join-Path $OspanelRoot 'modules\database\*\bin\mysql.exe'),
        (Join-Path $OspanelRoot 'modules\Database\*\bin\mysql.exe')
    )

    foreach ($pattern in $patterns) {
        $exe = Get-ChildItem -Path $pattern -ErrorAction SilentlyContinue |
            Sort-Object { $_.FullName } -Descending |
            Select-Object -First 1 -ExpandProperty FullName
        if ($exe) { return $exe }
    }

    return $null
}

function Get-OspanelMySqlIniPath {
    param([string]$OspanelRoot = (Get-OspanelRoot))

    $ini = Get-ChildItem -Path (Join-Path $OspanelRoot 'modules\MySQL-*\my.ini') -ErrorAction SilentlyContinue |
        Sort-Object { $_.FullName } -Descending |
        Select-Object -First 1 -ExpandProperty FullName

    if ($ini) { return $ini }

    $legacy = @(
        (Join-Path $OspanelRoot 'modules\database'),
        (Join-Path $OspanelRoot 'modules\Database')
    )
    foreach ($root in $legacy) {
        if (-not (Test-Path $root)) { continue }
        $found = Get-ChildItem -Path $root -Recurse -Filter 'my.ini' -ErrorAction SilentlyContinue |
            Select-Object -First 1 -ExpandProperty FullName
        if ($found) { return $found }
    }

    return $null
}

function Get-OspanelMySqlBindHost {
    param([string]$OspanelRoot = (Get-OspanelRoot))

    $ini = Get-OspanelMySqlIniPath -OspanelRoot $OspanelRoot
    if (-not $ini) { return $null }

    foreach ($line in Get-Content $ini) {
        if ($line -match '^\s*bind_address\s*=\s*(.+)\s*$') {
            return $Matches[1].Trim().Trim('"').Trim("'")
        }
    }

    return $null
}

function Get-OspanelMySqlHostCandidates {
    param(
        [string[]]$Preferred = @(),
        [string]$OspanelRoot = (Get-OspanelRoot)
    )

    $detected = Get-OspanelMySqlBindHost -OspanelRoot $OspanelRoot
    $fallback = @('127.0.1.21', '127.0.0.1', 'localhost')
    $all = @()
    if ($detected) { $all += $detected }
    $all += $Preferred + $fallback

    return $all | Where-Object { $_ } | Select-Object -Unique
}

function Add-OspanelMySqlToPath {
    param([string]$OspanelRoot = (Get-OspanelRoot))

    $client = Find-OspanelMySqlClient -OspanelRoot $OspanelRoot
    if (-not $client) { return $false }

    $bin = Split-Path -Parent $client
    if ($env:Path -notlike "*$bin*") {
        $env:Path = "$bin;$env:Path"
    }

    return $true
}
