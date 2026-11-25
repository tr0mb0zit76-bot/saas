<?php

namespace App\Jobs\Integrations;

use App\Jobs\Middleware\RequireJobTenantContext;
use App\Jobs\Middleware\SetTenantFromJob;
use App\Models\Tenant;
use App\Services\Saas\TenantConnectorService;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Stub sync entrypoint — proves TenantContext + feature gate (ADR-022).
 */
class RunTenantConnectorJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $tenantId,
        public string $connectorId,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            new SetTenantFromJob,
            new RequireJobTenantContext,
        ];
    }

    public function handle(TenantConnectorService $connectors): void
    {
        $tenant = TenantContext::get() ?? Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            throw new RuntimeException('Tenant missing.');
        }

        if (! $tenant->featureEnabled('integrations')) {
            throw new RuntimeException('feature:integrations is off.');
        }

        $row = collect($connectors->listForPlatform($tenant))
            ->first(fn (array $item): bool => $item['id'] === $this->connectorId);

        if (! is_array($row) || ! ($row['enabled'] ?? false)) {
            throw new RuntimeException('Connector disabled or missing.');
        }

        // Adapter runtime is deal-specific; this job only validates isolation gates.
    }
}
