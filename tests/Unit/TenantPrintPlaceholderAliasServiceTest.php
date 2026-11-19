<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Services\PrintForm\TenantPrintPlaceholderAliasService;
use App\Services\PrintFormVariableCatalog;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantPrintPlaceholderAliasServiceTest extends TestCase
{
    #[Test]
    public function rejects_path_outside_platform_catalog(): void
    {
        $service = new TenantPrintPlaceholderAliasService(new PrintFormVariableCatalog);

        $this->expectException(ValidationException::class);

        $service->normalizeAndValidate([
            ['macro' => 'my_inn', 'path' => 'order.not_a_real_path'],
        ]);
    }

    #[Test]
    public function accepts_catalog_path_and_normalizes_macro_braces(): void
    {
        $service = new TenantPrintPlaceholderAliasService(new PrintFormVariableCatalog);
        $path = 'customer.inn';

        $rows = $service->normalizeAndValidate([
            ['macro' => '${my_inn}', 'path' => $path],
        ]);

        $this->assertSame([
            ['macro' => 'my_inn', 'path' => $path],
        ], $rows);
    }

    #[Test]
    public function map_for_current_tenant_empty_without_context(): void
    {
        $service = new TenantPrintPlaceholderAliasService(new PrintFormVariableCatalog);

        $this->assertSame([], $service->mapForCurrentTenant());
    }

    #[Test]
    public function list_for_tenant_skips_invalid_stored_rows(): void
    {
        $service = new TenantPrintPlaceholderAliasService(new PrintFormVariableCatalog);
        $tenant = new Tenant([
            'settings' => [
                'print_placeholder_aliases' => [
                    ['macro' => 'ok', 'path' => 'customer.inn'],
                    ['macro' => 'bad', 'path' => 'nope.missing'],
                ],
            ],
        ]);

        $list = $service->listForTenant($tenant);

        $this->assertCount(1, $list);
        $this->assertSame('ok', $list[0]['macro']);
        $this->assertSame('customer.inn', $list[0]['path']);
    }
}
