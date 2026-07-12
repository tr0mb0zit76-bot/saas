<?php

namespace App\Services\Saas;

use App\Models\Tenant;
use App\Models\TenantUsageLog;
use App\Support\TenantContext;
use Illuminate\Support\Facades\File;
use ZipArchive;

final class TenantExportService
{
    public function __construct(
        private readonly TenantUsageMeter $usageMeter,
    ) {}

    /**
     * Export tenant metadata + usage snapshot to a ZIP (152-ФЗ / churn prep).
     */
    public function exportToZip(Tenant $tenant): string
    {
        $exportDir = storage_path('app/exports/tenant-'.$tenant->id.'-'.now()->format('Ymd-His'));
        File::ensureDirectoryExists($exportDir);

        TenantContext::runAs($tenant, function () use ($tenant, $exportDir): void {
            $snapshot = [
                'tenant' => [
                    'id' => $tenant->id,
                    'slug' => $tenant->slug,
                    'name' => $tenant->name,
                    'status' => $tenant->status,
                    'plan' => $tenant->planKey(),
                    'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
                    'exported_at' => now()->toIso8601String(),
                ],
                'usage' => [
                    'users_count' => $tenant->users()->count(),
                    'orders_count' => $tenant->orders()->count(),
                    'storage_bytes' => $this->usageMeter->measureStorageBytes($tenant),
                ],
                'latest_usage_log' => $tenant->usageLogs()->orderByDesc('recorded_on')->first()?->toArray(),
            ];

            File::put(
                $exportDir.'/manifest.json',
                json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            );
        });

        $zipPath = $exportDir.'.zip';

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($exportDir.'/manifest.json', 'manifest.json');
        $zip->close();

        File::deleteDirectory($exportDir);

        return $zipPath;
    }
}
