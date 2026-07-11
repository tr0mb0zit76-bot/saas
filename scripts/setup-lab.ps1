# One-command SaaS lab setup (idempotent where possible).
# Orchestrator entry point — minimal human involvement.
#
# Usage:
#   pwsh -File scripts/setup-lab.ps1
#   pwsh -File scripts/setup-lab.ps1 -SkipBootstrap   # if code already copied
#   pwsh -File scripts/setup-lab.ps1 -V5Root D:\crm\v5.local

param(
    [string]$V5Root = 'C:\OSPanel\home\v5.local',
    [switch]$SkipBootstrap,
    [switch]$SkipBuild
)

$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $repoRoot

function Update-MigrationStep {
    param([string]$StepId, [string]$Status = 'done', [string]$Note = '')
    $stateFile = Join-Path $repoRoot 'docs\sync\migration-state.json'
    if (-not (Test-Path $stateFile)) { return }
    $state = Get-Content $stateFile -Raw | ConvertFrom-Json
    if ($state.steps.PSObject.Properties.Name -contains $StepId) {
        $state.steps.$StepId.status = $Status
        if ($Note) { $state.steps.$StepId.note = $Note }
    }
    $state.updated_at = (Get-Date).ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ')
    $state | ConvertTo-Json -Depth 10 | Set-Content $stateFile
}

Write-Host '=== SaaS Lab Setup ===' -ForegroundColor Cyan

# M0 checks
Write-Host '[M0] Prerequisites...'
php -v | Out-Null; Update-MigrationStep 'M0.2'
composer -V | Out-Null; Update-MigrationStep 'M0.3'
node -v | Out-Null; Update-MigrationStep 'M0.4'

if (-not (Test-Path $V5Root)) {
    Write-Error "v5.local not found at $V5Root. Pass -V5Root or clone v5."
}
Update-MigrationStep 'M0.5'

# M1 Bootstrap
if (-not $SkipBootstrap -and -not (Test-Path (Join-Path $repoRoot 'artisan'))) {
    Write-Host '[M1] Bootstrap from v5...'
    & (Join-Path $repoRoot 'scripts\bootstrap-from-v5.ps1') -V5Root $V5Root
    Update-MigrationStep 'M1.1'
    Update-MigrationStep 'M1.2'
} elseif (Test-Path (Join-Path $repoRoot 'artisan')) {
    Write-Host '[M1] Skip bootstrap — artisan exists'
    Update-MigrationStep 'M1.1' 'done' 'skipped — already bootstrapped'
    Update-MigrationStep 'M1.2' 'done' 'skipped'
}

# M2 Environment
Write-Host '[M2] Environment...'
$envExample = Join-Path $repoRoot '.env.example'
$envFile = Join-Path $repoRoot '.env'
if ((Test-Path $envExample) -and -not (Test-Path $envFile)) {
    Copy-Item $envExample $envFile
    Write-Host 'Created .env from .env.example'
}
Update-MigrationStep 'M2.1'

if (Test-Path $envFile) {
    $lines = Get-Content $envFile
    $replacements = @{
        'APP_URL'                  = 'http://saas.local'
        'CRM_DOMAIN'               = 'saas.local'
        'SHOWCASE_DOMAIN'          = 'saas.local'
        'APP_ENV'                  = 'local'
        'APP_DEBUG'                = 'true'
        'DB_CONNECTION'            = 'mysql'
        'DB_HOST'                  = '127.0.1.21'
        'DB_PORT'                  = '3306'
        'DB_DATABASE'              = 'saas_crm'
        'DB_USERNAME'              = 'root'
        'DB_PASSWORD'              = ''
        'SAAS_DEFAULT_TENANT_SLUG'   = 'demo'
    }
    foreach ($key in $replacements.Keys) {
        $val = $replacements[$key]
        $idx = [array]::FindIndex($lines, [Predicate[string]] { param($l) $l -match "^$key=" })
        if ($idx -ge 0) {
            $lines[$idx] = "$key=$val"
        } else {
            $lines += "$key=$val"
        }
    }
    # Uncomment DB block if still commented
    $lines = $lines | ForEach-Object {
        if ($_ -match '^#\s*(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)=') {
            $_ -replace '^#\s*', ''
        } else { $_ }
    }
    Set-Content -Path $envFile -Value ($lines -join "`n")
    Update-MigrationStep 'M2.2'
}

& (Join-Path $repoRoot 'scripts\provision-database.ps1')
Update-MigrationStep 'M2.3'

if (Test-Path (Join-Path $repoRoot 'composer.json')) {
    Write-Host 'composer install...'
    $composerCmd = Get-Command composer -ErrorAction SilentlyContinue
    if ($composerCmd) {
        & $composerCmd.Source install --no-interaction
    } elseif (Test-Path (Join-Path $repoRoot 'composer.phar')) {
        php (Join-Path $repoRoot 'composer.phar') install --no-interaction
    } else {
        Write-Error "composer not in PATH. Run: pwsh -File scripts/finish-lab-setup.ps1"
    }
    if (-not (Test-Path (Join-Path $repoRoot 'vendor\autoload.php'))) {
        Write-Error 'composer install did not create vendor/autoload.php'
    }
    Update-MigrationStep 'M2.4'
}

if (Test-Path (Join-Path $repoRoot 'package.json')) {
    npm ci
    Update-MigrationStep 'M2.5'
}

if (Test-Path (Join-Path $repoRoot 'artisan')) {
    $keyLine = Get-Content $envFile -ErrorAction SilentlyContinue | Where-Object { $_ -match '^APP_KEY=' } | Select-Object -First 1
    if (-not $keyLine -or $keyLine -eq 'APP_KEY=' -or $keyLine -match 'APP_KEY=\s*$') {
        php artisan key:generate --force
    }
    php artisan config:clear
    php artisan route:clear
    Update-MigrationStep 'M2.6'
}

# M3 Schema
if (Test-Path (Join-Path $repoRoot 'artisan')) {
    Write-Host '[M3] Schema...'
    foreach ($sub in @('storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache')) {
        $p = Join-Path $repoRoot ($sub -replace '/', '\')
        if (-not (Test-Path $p)) { New-Item -ItemType Directory -Path $p -Force | Out-Null }
    }
    php artisan migrate --force --schema-path=database/schema/.skip-mysql-cli-load
    Update-MigrationStep 'M3.1'

    try {
        php artisan db:seed --class=SaasDemoSeeder --force
        Update-MigrationStep 'M3.2'
        Update-MigrationStep 'M3.3'
        php artisan db:seed --class=TenantDemoSeeder --force
        php artisan saas:smoke-lab
        Update-MigrationStep 'M4.4'
    } catch {
        Write-Warning 'Seeder/smoke failed — check migrate and logs'
    }

    if (-not $SkipBuild -and (Test-Path (Join-Path $repoRoot 'package.json'))) {
        npm run build
        Update-MigrationStep 'M3.4'
    }
}

Write-Host ''
Write-Host '=== Setup complete ===' -ForegroundColor Green
Write-Host 'Open: http://saas.local' -ForegroundColor Green
Write-Host 'Login: admin@saas.local / password' -ForegroundColor Green
& (Join-Path $repoRoot 'scripts\migration-status.ps1')
