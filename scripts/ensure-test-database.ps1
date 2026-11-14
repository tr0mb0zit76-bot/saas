# Отдельная БД для php artisan test — не трогает lab (saas_crm).
#
# Usage:
#   pwsh -File scripts/ensure-test-database.ps1

$ErrorActionPreference = 'Stop'
$repoRoot = 'C:\OSPanel\home\saas\saas.local'
if (-not (Test-Path (Join-Path $repoRoot 'artisan'))) {
    $repoRoot = Split-Path -Parent $PSScriptRoot
}
Set-Location $repoRoot

$example = Join-Path $repoRoot '.env.testing.example'
$testing = Join-Path $repoRoot '.env.testing'
$labEnv = Join-Path $repoRoot '.env'

if (-not (Test-Path $testing)) {
    if (-not (Test-Path $example)) {
        Write-Error 'Missing .env.testing.example'
    }
    Copy-Item $example $testing
    Write-Host 'Created .env.testing from .env.testing.example' -ForegroundColor Green
}

$testingLines = Get-Content $testing
$hasKey = $testingLines | Where-Object { $_ -match '^APP_KEY=.+$' -and $_ -notmatch '^APP_KEY=$' } | Select-Object -First 1
if (-not $hasKey -and (Test-Path $labEnv)) {
    $labKey = Get-Content $labEnv | Where-Object { $_ -match '^APP_KEY=' } | Select-Object -First 1
    if ($labKey) {
        $idx = [array]::FindIndex($testingLines, [Predicate[string]] { param($l) $l -match '^APP_KEY=' })
        if ($idx -ge 0) {
            $testingLines[$idx] = $labKey
        } else {
            $testingLines = @($labKey) + $testingLines
        }
        Set-Content -Path $testing -Value ($testingLines -join "`n")
        Write-Host 'Copied APP_KEY from .env into .env.testing' -ForegroundColor Green
    }
}

if (Test-Path $labEnv) {
    $labHost = (Get-Content $labEnv | Where-Object { $_ -match '^DB_HOST=' } | Select-Object -First 1) -replace '^DB_HOST=', ''
    if ($labHost) {
        $content = Get-Content $testing -Raw
        if ($content -match '(?m)^DB_HOST=') {
            $content = $content -replace '(?m)^DB_HOST=.*', "DB_HOST=$labHost"
        } else {
            $content += "`nDB_HOST=$labHost"
        }
        Set-Content -Path $testing -Value $content.TrimEnd()
    }
}

& (Join-Path $repoRoot 'scripts\provision-database.ps1') -DatabaseName saas_crm_test

$labDb = $null
$testDb = $null
if (Test-Path $labEnv) {
    $labDb = (Get-Content $labEnv | Where-Object { $_ -match '^DB_DATABASE=' } | Select-Object -First 1) -replace '^DB_DATABASE=', '' -replace '"', ''
}
if (Test-Path $testing) {
    $testDb = (Get-Content $testing | Where-Object { $_ -match '^DB_DATABASE=' } | Select-Object -First 1) -replace '^DB_DATABASE=', '' -replace '"', ''
}
if ($labDb -and $testDb -and $labDb -eq $testDb) {
    Write-Host "WARNING: .env and .env.testing both use DB_DATABASE=$labDb — tests will wipe lab!" -ForegroundColor Red
    exit 1
}

Write-Host "Test DB ready: $testDb (lab stays on $labDb)" -ForegroundColor Green
