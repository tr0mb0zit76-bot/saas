<?php

namespace Tests\Unit\Support;

use App\Support\IntegrationConnectorRegistry;
use InvalidArgumentException;
use Tests\TestCase;

class IntegrationConnectorRegistryTest extends TestCase
{
    public function test_catalog_for_tenant_includes_major_open_api_banks_and_excludes_nothing_required(): void
    {
        $keys = array_column(IntegrationConnectorRegistry::catalogForTenant(), 'key');

        foreach (['one_c_bp', 't_bank', 'tochka', 'sber', 'alfa', 'vtb_bank'] as $expected) {
            $this->assertContains($expected, $keys);
        }

        $byKey = [];
        foreach (IntegrationConnectorRegistry::catalogForTenant() as $row) {
            $byKey[$row['key']] = $row;
        }

        $this->assertTrue($byKey['t_bank']['docs_open']);
        $this->assertTrue($byKey['tochka']['docs_open']);
        $this->assertTrue($byKey['sber']['docs_open']);
        $this->assertTrue($byKey['alfa']['docs_open']);
        $this->assertFalse($byKey['vtb_bank']['docs_open']);
        $this->assertSame('ibk_soap_gost', $byKey['vtb_bank']['auth_model']);
        $this->assertSame('bearer_token', $byKey['t_bank']['auth_model']);
    }

    public function test_assert_tenant_bindable_rejects_unknown_adapter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        IntegrationConnectorRegistry::assertTenantBindable('not_a_real_adapter');
    }
}
