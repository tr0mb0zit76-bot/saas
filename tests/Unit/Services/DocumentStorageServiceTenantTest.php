<?php

namespace Tests\Unit\Services;

use App\Services\DocumentStorageService;
use App\Support\TenantContext;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\Storage;
use Tests\SaasTestCase;

class DocumentStorageServiceTenantTest extends SaasTestCase
{
    public function test_store_order_upload_uses_tenant_storage_prefix(): void
    {
        Storage::fake('tenant_local');
        config([
            'document_storage.driver' => 'local',
            'tenant_storage.disk' => 'tenant_local',
            'tenant_storage.use_for_documents' => true,
        ]);

        $this->bindDemoTenant();

        $file = \Illuminate\Http\UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $result = app(DocumentStorageService::class)->storeOrderUpload($file, 99);

        $this->assertSame('tenant_local', $result['storage_driver']);
        $this->assertTrue(TenantStorage::exists($result['file_path']));
    }

    public function test_legacy_nextcloud_metadata_maps_to_tenant_under_context(): void
    {
        Storage::fake('tenant_local');
        config([
            'document_storage.driver' => 'local',
            'tenant_storage.disk' => 'tenant_local',
            'tenant_storage.use_for_documents' => true,
        ]);

        $this->bindDemoTenant();

        $mapped = app(DocumentStorageService::class)->resolveStoredDriver('nextcloud');

        $this->assertSame('tenant_local', $mapped);
    }

    public function test_put_without_driver_uses_tenant_storage_under_context(): void
    {
        Storage::fake('tenant_local');
        config([
            'document_storage.driver' => 'local',
            'tenant_storage.disk' => 'tenant_local',
            'tenant_storage.use_for_documents' => true,
        ]);

        $this->bindDemoTenant();

        $storage = app(DocumentStorageService::class);
        $storage->put('order_documents/7/print.pdf', 'pdf-bytes');

        $this->assertTrue(TenantStorage::exists('order_documents/7/print.pdf'));
        $this->assertSame('pdf-bytes', TenantStorage::get('order_documents/7/print.pdf'));
    }

    private function bindDemoTenant(): void
    {
        TenantContext::bypass(true);
        $tenant = \App\Models\Tenant::query()->create([
            'slug' => 'doc-'.uniqid(),
            'name' => 'Doc tenant',
            'status' => 'active',
            'plan' => 'start',
        ]);
        TenantContext::bypass(false);
        TenantContext::set($tenant);
    }
}
