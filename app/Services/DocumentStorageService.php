<?php

namespace App\Services;

use App\Support\TenantContext;
use App\Support\TenantStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Document blobs for Traklo Pro: TenantStorage (tenants/{id}/…) under TenantContext,
 * otherwise local disk. Nextcloud is not supported in this SaaS codebase (ADR-005).
 */
class DocumentStorageService
{
    public const DRIVER_LOCAL = 'local';

    /** @deprecated Kept only to recognize legacy metadata rows; maps via resolveStoredDriver(). */
    public const DRIVER_NEXTCLOUD = 'nextcloud';

    public const DRIVER_TENANT = 'tenant';

    private readonly string $configuredDriver;

    public function __construct()
    {
        $driver = (string) config('document_storage.driver', self::DRIVER_LOCAL);
        $allowed = [
            self::DRIVER_LOCAL,
            self::DRIVER_TENANT,
            'tenant_local',
            'tenant_s3',
        ];

        if (! in_array($driver, $allowed, true)) {
            if ($driver === self::DRIVER_NEXTCLOUD) {
                Log::warning('document_storage.nextcloud_config_ignored', [
                    'hint' => 'Use TENANT_STORAGE_DISK; Nextcloud removed from SaaS.',
                ]);
            }
            $driver = self::DRIVER_LOCAL;
        }

        if (in_array($driver, ['tenant_local', 'tenant_s3'], true)) {
            $driver = self::DRIVER_TENANT;
        }

        $this->configuredDriver = $driver;
    }

    public function configuredDriver(): string
    {
        if ($this->shouldForceTenantWrites()) {
            return $this->tenantStorageDriverLabel();
        }

        return $this->configuredDriver === self::DRIVER_TENANT
            ? $this->tenantStorageDriverLabel()
            : $this->configuredDriver;
    }

    /**
     * Normalize storage_driver from DB / metadata for get/delete.
     * Legacy "nextcloud" → tenant disk when context is set, else local.
     */
    public function resolveStoredDriver(?string $driver): string
    {
        $driver = $driver !== null ? trim($driver) : '';

        if (in_array($driver, [self::DRIVER_TENANT, 'tenant_local', 'tenant_s3'], true)) {
            return $driver === self::DRIVER_TENANT
                ? $this->tenantStorageDriverLabel()
                : $driver;
        }

        if ($driver === self::DRIVER_NEXTCLOUD) {
            Log::warning('document_storage.legacy_nextcloud_driver', [
                'tenant_id' => TenantContext::id(),
            ]);

            return $this->shouldForceTenantWrites()
                ? $this->tenantStorageDriverLabel()
                : self::DRIVER_LOCAL;
        }

        if ($this->shouldForceTenantWrites() && ($driver === '' || $driver === self::DRIVER_LOCAL)) {
            return $this->tenantStorageDriverLabel();
        }

        return self::DRIVER_LOCAL;
    }

    /**
     * @return array{original_name: string, file_path: string, file_size: int, mime_type: string|null, storage_driver: string}
     */
    public function storeOrderUpload(UploadedFile $file, ?int $orderId = null): array
    {
        $originalName = $file->getClientOriginalName();
        $directory = $orderId !== null
            ? 'order_documents/'.$orderId
            : 'order_documents/misc';
        $driver = $this->resolveWriteDriver();
        $path = $this->resolveUniquePathInDirectory($directory, $originalName, $driver);
        $contents = $file->get();
        $this->put($path, $contents, $driver);
        $size = $file->getSize();
        if ($size === false) {
            $size = strlen($contents);
        }

        return [
            'original_name' => $originalName,
            'file_path' => $path,
            'file_size' => (int) $size,
            'mime_type' => $file->getMimeType(),
            'storage_driver' => $driver,
        ];
    }

    /**
     * @return array{original_name: string, file_path: string, file_size: int, mime_type: string|null, storage_driver: string}
     */
    public function storeMailInboundAttachment(string $contents, string $originalName, int $mailboxUserId, int $messageId): array
    {
        $directory = 'mail_inbound/'.$mailboxUserId.'/'.$messageId;
        $driver = $this->resolveWriteDriver();
        $path = $this->resolveUniquePathInDirectory($directory, $originalName, $driver);
        $this->put($path, $contents, $driver);

        return [
            'original_name' => basename(str_replace('\\', '/', $originalName)),
            'file_path' => $path,
            'file_size' => strlen($contents),
            'mime_type' => null,
            'storage_driver' => $driver,
        ];
    }

    public function storeMailOutboundUpload(UploadedFile $file, int $senderUserId, ?int $orderId = null): array
    {
        $originalName = $file->getClientOriginalName();
        $directory = $orderId !== null
            ? 'mail_outbound/'.$senderUserId.'/order_'.$orderId
            : 'mail_outbound/'.$senderUserId;
        $driver = $this->resolveWriteDriver();
        $path = $this->resolveUniquePathInDirectory($directory, $originalName, $driver);
        $contents = $file->get();
        $this->put($path, $contents, $driver);
        $size = $file->getSize();
        if ($size === false) {
            $size = strlen($contents);
        }

        return [
            'original_name' => $originalName,
            'file_path' => $path,
            'file_size' => (int) $size,
            'mime_type' => $file->getMimeType(),
            'storage_driver' => $driver,
        ];
    }

    public function storeContractorUpload(UploadedFile $file, ?int $contractorId = null): array
    {
        $originalName = $file->getClientOriginalName();
        $directory = $contractorId !== null
            ? 'contractor_documents/'.$contractorId
            : 'contractor_documents/misc';
        $driver = $this->resolveWriteDriver();
        $path = $this->resolveUniquePathInDirectory($directory, $originalName, $driver);
        $contents = $file->get();
        $this->put($path, $contents, $driver);
        $size = $file->getSize();
        if ($size === false) {
            $size = strlen($contents);
        }

        return [
            'original_name' => $originalName,
            'file_path' => $path,
            'file_size' => (int) $size,
            'mime_type' => $file->getMimeType(),
            'storage_driver' => $driver,
        ];
    }

    public function put(string $path, string $contents, ?string $driver = null): void
    {
        if ($driver === null && $this->shouldForceTenantWrites()) {
            TenantStorage::put($path, $contents);

            return;
        }

        if ($this->usesTenantStorage($driver)) {
            TenantStorage::put($path, $contents);

            return;
        }

        Storage::disk(self::DRIVER_LOCAL)->put($path, $contents);
    }

    public function get(string $path, ?string $driver = null): string
    {
        $driver = $this->resolveStoredDriver($driver);

        if ($this->usesTenantStorage($driver)) {
            return (string) TenantStorage::get($path);
        }

        return (string) Storage::disk(self::DRIVER_LOCAL)->get($path);
    }

    public function delete(?string $path, ?string $driver = null): void
    {
        if (blank($path)) {
            return;
        }

        try {
            $driver = $this->resolveStoredDriver($driver);

            if ($this->usesTenantStorage($driver)) {
                TenantStorage::delete($path);

                return;
            }

            Storage::disk(self::DRIVER_LOCAL)->delete((string) $path);
        } catch (Throwable $exception) {
            Log::warning('document_storage.delete_failed', [
                'path' => $path,
                'driver' => $driver,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function size(string $path, ?string $driver = null, ?string $knownContents = null): int
    {
        if ($knownContents !== null) {
            return strlen($knownContents);
        }

        $driver = $this->resolveStoredDriver($driver);

        if ($this->usesTenantStorage($driver)) {
            return strlen((string) TenantStorage::get($path));
        }

        return (int) Storage::disk(self::DRIVER_LOCAL)->size($path);
    }

    public function exists(string $path, ?string $driver = null): bool
    {
        $driver = $this->resolveStoredDriver($driver);

        if ($this->usesTenantStorage($driver)) {
            return TenantStorage::exists($path);
        }

        return Storage::disk(self::DRIVER_LOCAL)->exists($path);
    }

    public function resolveOrderDocumentPath(int $orderId, string $preferredFilename, ?string $driver = null): string
    {
        return $this->resolveUniquePathInDirectory(
            'order_documents/'.$orderId,
            $preferredFilename,
            $driver,
        );
    }

    public function filenameWithVariant(string $filename, string $variant): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        if ($basename === '') {
            $basename = 'document';
        }

        if ($extension !== '') {
            return $basename.$variant.'.'.$extension;
        }

        return $basename.$variant;
    }

    public function sanitizeStorageFilename(string $filename): string
    {
        $basename = basename(str_replace('\\', '/', $filename));
        $basename = preg_replace('/[\x00-\x1F\x7F]/u', '', $basename) ?? '';
        $basename = preg_replace('/[\/\\\\:*?"<>|]/u', '-', $basename) ?? '';
        $basename = preg_replace('/-+/', '-', $basename) ?? '';
        $basename = preg_replace('/\s+/u', ' ', $basename) ?? '';
        $basename = trim($basename, " \t\n\r\0\x0B.-");

        while (str_ends_with($basename, '.')) {
            $basename = rtrim($basename, '.');
        }

        if ($basename === '' || $basename === '.' || $basename === '..') {
            $basename = 'document';
        }

        $extension = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));
        $nameWithoutExtension = trim((string) pathinfo($basename, PATHINFO_FILENAME), " \t\n\r\0\x0B.-");
        $maxBasenameLength = 200;

        if ($extension !== '' && preg_match('/^[a-z0-9]{1,10}$/i', $extension) !== 1) {
            $extension = '';
        }

        if ($nameWithoutExtension === '') {
            $nameWithoutExtension = 'document';
        }

        if ($extension !== '' && mb_strlen($nameWithoutExtension) > $maxBasenameLength) {
            $nameWithoutExtension = mb_substr($nameWithoutExtension, 0, $maxBasenameLength);
        }

        if ($extension !== '') {
            return $nameWithoutExtension.'.'.$extension;
        }

        if (mb_strlen($nameWithoutExtension) > $maxBasenameLength) {
            $nameWithoutExtension = mb_substr($nameWithoutExtension, 0, $maxBasenameLength);
        }

        return $nameWithoutExtension;
    }

    private function resolveUniquePathInDirectory(string $directory, string $preferredFilename, ?string $driver = null): string
    {
        $sanitized = $this->sanitizeStorageFilename($preferredFilename);
        $directory = trim(str_replace('\\', '/', $directory), '/');
        $candidate = $sanitized;
        $attempt = 1;

        while ($this->exists($directory.'/'.$candidate, $driver)) {
            $attempt++;
            $extension = pathinfo($sanitized, PATHINFO_EXTENSION);
            $basename = pathinfo($sanitized, PATHINFO_FILENAME);

            if ($extension !== '') {
                $candidate = sprintf('%s (%d).%s', $basename, $attempt, $extension);
            } else {
                $candidate = sprintf('%s (%d)', $basename, $attempt);
            }

            if ($attempt > 99) {
                $suffix = Str::uuid()->toString();
                $candidate = $extension !== ''
                    ? sprintf('%s-%s.%s', $basename, $suffix, $extension)
                    : sprintf('%s-%s', $basename, $suffix);

                break;
            }
        }

        return $directory.'/'.$candidate;
    }

    private function resolveWriteDriver(?string $preferred = null): string
    {
        $preferred ??= $this->configuredDriver;

        if ($this->shouldForceTenantWrites()) {
            return $this->tenantStorageDriverLabel();
        }

        if (in_array($preferred, [self::DRIVER_TENANT, 'tenant_local', 'tenant_s3'], true)) {
            return self::DRIVER_LOCAL;
        }

        return self::DRIVER_LOCAL;
    }

    private function shouldForceTenantWrites(): bool
    {
        return (bool) config('tenant_storage.use_for_documents', true)
            && TenantContext::id() !== null;
    }

    private function usesTenantStorage(?string $driver): bool
    {
        if (in_array($driver, [self::DRIVER_TENANT, 'tenant_local', 'tenant_s3'], true)) {
            return TenantContext::id() !== null;
        }

        return (bool) config('tenant_storage.use_for_documents', true)
            && TenantContext::id() !== null
            && ($driver === null || $driver === self::DRIVER_LOCAL);
    }

    private function tenantStorageDriverLabel(): string
    {
        return str_contains(TenantStorage::diskName(), 's3') ? 'tenant_s3' : 'tenant_local';
    }
}
