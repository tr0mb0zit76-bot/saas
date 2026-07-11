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
        config(['tenant_storage.disk' => 'tenant_local', 'tenant_storage.use_for_documents' => true]);

        TenantContext::bypass(true);
        $tenant = \App\Models\Tenant::query()->create([
            'slug' => 'doc-'.uniqid(),
            'name' => 'Doc tenant',
            'status' => 'active',
            'plan' => 'start',
        ]);
        TenantContext::bypass(false);
        TenantContext::set($tenant);

        $file = \Illuminate\Http\UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $result = app(DocumentStorageService::class)->storeOrderUpload($file, 99);

        $this->assertSame('tenant_local', $result['storage_driver']);
        $this->assertTrue(TenantStorage::exists($result['file_path']));
    }
}
