<?php

namespace App\Support;

use App\Models\Tenant;
use App\Services\Saas\TenantUsageLimiter;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Tenant-scoped file paths. Lab uses local disk; production targets S3-compatible storage.
 *
 * Object key layout (same for local and S3):
 *   tenants/{tenant_id}/order_documents/{orderId}/file.pdf
 */
final class TenantStorage
{
    public static function diskName(): string
    {
        return (string) config('tenant_storage.disk', 'tenant_local');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    public static function tenantId(): int
    {
        $id = TenantContext::id();

        if ($id === null) {
            throw new InvalidArgumentException('Tenant context is required for tenant storage.');
        }

        return $id;
    }

    /**
     * Prefix relative path with tenants/{id}/ — never trust client-supplied tenant segment.
     */
    public static function path(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        self::assertSafeRelativePath($relativePath);

        return 'tenants/'.self::tenantId().'/'.$relativePath;
    }

    /**
     * Defense-in-depth: stored object keys must stay under tenants/{current}/.
     */
    public static function assertOwnedObjectKey(string $objectKey): void
    {
        $normalized = ltrim(str_replace('\\', '/', $objectKey), '/');
        $prefix = 'tenants/'.self::tenantId().'/';

        if ($normalized === '' || str_contains($normalized, '..') || ! str_starts_with($normalized, $prefix)) {
            throw new InvalidArgumentException('Stored object key is outside the current tenant prefix.');
        }
    }

    private static function assertSafeRelativePath(string $relativePath): void
    {
        if ($relativePath === '' || str_starts_with($relativePath, '/') || preg_match('#(^|/)\.\.(/|$)#', $relativePath) === 1) {
            throw new InvalidArgumentException('Tenant storage path must be relative and must not contain .. segments.');
        }
    }

    public static function put(string $relativePath, mixed $contents): bool
    {
        if (is_string($contents)) {
            app(TenantUsageLimiter::class)->assertCanStoreBytes(strlen($contents));
        }

        return self::disk()->put(self::path($relativePath), $contents);
    }

    public static function get(string $relativePath): ?string
    {
        $full = self::path($relativePath);

        return self::disk()->exists($full) ? self::disk()->get($full) : null;
    }

    public static function delete(string $relativePath): bool
    {
        return self::disk()->delete(self::path($relativePath));
    }

    public static function exists(string $relativePath): bool
    {
        return self::disk()->exists(self::path($relativePath));
    }

    /**
     * Provision empty prefix for a new tenant (idempotent).
     */
    public static function provisionFor(Tenant $tenant): void
    {
        TenantContext::runAs($tenant, function () use ($tenant): void {
            $marker = self::path('.tenant-provisioned');
            if (! self::disk()->exists($marker)) {
                self::disk()->put($marker, (string) now()->toIso8601String());
            }
        });
    }

    /**
     * S3 object key for external systems (metrics, lifecycle rules).
     */
    public static function objectKey(string $relativePath): string
    {
        $prefix = trim((string) config('tenant_storage.root_prefix', ''), '/');
        $path = self::path($relativePath);

        return $prefix !== '' ? $prefix.'/'.$path : $path;
    }
}
