<?php

namespace App\Services\Saas;

use App\Models\Tenant;
use App\Support\TenantContext;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
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
            TenantContext::bypass(false);
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

            foreach ([
                'users',
                'contractors',
                'contractor_contacts',
                'fleet_drivers',
                'orders',
                'mail_messages',
                'ai_interaction_events',
            ] as $table) {
                $this->dumpTable($table, $tenant->id, $exportDir);
            }
        });

        $storagePrefix = 'tenants/'.$tenant->id;
        if (TenantStorage::disk()->exists($storagePrefix)) {
            foreach (TenantStorage::disk()->allFiles($storagePrefix) as $file) {
                $contents = TenantStorage::disk()->get($file);
                if ($contents === null) {
                    continue;
                }
                $relative = 'files/'.ltrim(substr($file, strlen($storagePrefix)), '/');
                $target = $exportDir.'/'.$relative;
                File::ensureDirectoryExists(dirname($target));
                File::put($target, $contents);
            }
        }

        $zipPath = $exportDir.'.zip';

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $files = File::allFiles($exportDir);
        foreach ($files as $file) {
            $zip->addFile($file->getPathname(), $file->getRelativePathname());
        }
        $zip->close();

        File::deleteDirectory($exportDir);

        return $zipPath;
    }

    private function dumpTable(string $table, int $tenantId, string $exportDir): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
            return;
        }

        $hidden = ['password', 'remember_token', 'mail_imap_secret'];
        $columns = array_values(array_filter(
            Schema::getColumnListing($table),
            fn (string $column): bool => ! in_array($column, $hidden, true),
        ));

        $rows = DB::table($table)->where('tenant_id', $tenantId)->get($columns);

        File::put(
            $exportDir.'/'.$table.'.json',
            json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }
}
