<?php

namespace Tests\Unit\Support;

use App\Models\Tenant;
use App\Support\TenantContext;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\Storage;
use Tests\SaasTestCase;

class TenantStorageTest extends SaasTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('tenant_local');
        config(['tenant_storage.disk' => 'tenant_local']);
    }

    public function test_path_prefixes_with_tenant_id(): void
    {
        TenantContext::bypass(true);
        $tenant = Tenant::query()->create([
            'slug' => 'store-'.uniqid(),
            'name' => 'Storage test',
            'status' => 'active',
            'plan' => 'start',
        ]);
        TenantContext::bypass(false);
        TenantContext::set($tenant);

        $this->assertSame(
            'tenants/'.$tenant->id.'/order_documents/1/doc.pdf',
            TenantStorage::path('order_documents/1/doc.pdf'),
        );
    }

    public function test_put_and_get_roundtrip(): void
    {
        TenantContext::bypass(true);
        $tenant = Tenant::query()->create([
            'slug' => 'put-'.uniqid(),
            'name' => 'Put test',
            'status' => 'active',
            'plan' => 'start',
        ]);
        TenantContext::bypass(false);
        TenantContext::set($tenant);

        TenantStorage::put('test/hello.txt', 'hello');
        $this->assertSame('hello', TenantStorage::get('test/hello.txt'));
    }

    public function test_throws_without_tenant_context(): void
    {
        TenantContext::clear();

        $this->expectException(\InvalidArgumentException::class);
        TenantStorage::path('any/file.txt');
    }
}
