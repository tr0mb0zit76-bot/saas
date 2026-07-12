<?php

namespace App\Services\Saas;

use App\Models\TenantAuditLog;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class TenantAuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        ?int $tenantId,
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        if (! Schema::hasTable('tenant_audit_logs')) {
            return;
        }

        try {
            TenantAuditLog::query()->create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $this->redact($oldValues),
                'new_values' => $this->redact($newValues),
                'ip_address' => request()->ip(),
            ]);
        } catch (Throwable) {
            // Audit must not break business flow.
        }
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $redacted = $values;

        foreach (['password', 'token', 'api_key', 'secret'] as $key) {
            if (array_key_exists($key, $redacted)) {
                $redacted[$key] = '[redacted]';
            }
        }

        return $redacted;
    }
}
