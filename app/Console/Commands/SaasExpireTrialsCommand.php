<?php

namespace App\Console\Commands;

use App\Services\Saas\TenantBillingService;
use Illuminate\Console\Command;

class SaasExpireTrialsCommand extends Command
{
    protected $signature = 'saas:expire-trials';

    protected $description = 'Suspend tenants whose trial period has ended';

    public function handle(TenantBillingService $billing): int
    {
        $count = $billing->expireTrials(now());

        $this->info("Suspended {$count} expired trial tenant(s).");

        return self::SUCCESS;
    }
}
