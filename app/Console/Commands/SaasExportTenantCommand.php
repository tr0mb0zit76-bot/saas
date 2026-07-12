<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Saas\TenantExportService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class SaasExportTenantCommand extends Command
{
    protected $signature = 'saas:export-tenant {slug : Tenant slug}';

    protected $description = 'Export tenant manifest ZIP (152-ФЗ / ops)';

    public function handle(TenantExportService $export): int
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->where('slug', $this->argument('slug'))->first();

        TenantContext::bypass(false);

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $path = $export->exportToZip($tenant);

        $this->info("Export written: {$path}");

        return self::SUCCESS;
    }
}
