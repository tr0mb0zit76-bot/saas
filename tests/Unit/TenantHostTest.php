<?php

namespace Tests\Unit;

use App\Support\TenantHost;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

class TenantHostTest extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_prod_apex_crm_domain_keeps_full_base_host(): void
    {
        config([
            'app.url' => 'https://traklo.pro',
            'app.crm_domain' => 'traklo.pro',
        ]);

        $this->assertSame(
            'https://akvila_prime.traklo.pro/login',
            TenantHost::url('akvila_prime', '/login'),
        );
    }

    public function test_lab_same_host_omits_subdomain(): void
    {
        config([
            'app.url' => 'http://saas.local',
            'app.crm_domain' => 'saas.local',
        ]);

        $this->assertSame('http://saas.local/login', TenantHost::url('demo', '/login'));
    }
}
