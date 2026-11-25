<?php

namespace Tests\Unit;

use App\Support\ShowcaseUrl;
use Illuminate\Http\Request;
use Tests\TestCase;

class ShowcaseUrlTest extends TestCase
{
    public function test_tenant_subdomain_gets_absolute_showcase_apex(): void
    {
        config([
            'app.showcase_hosts' => ['traklo.pro', 'www.traklo.pro'],
            'app.crm_domain' => 'traklo.pro',
            'app.same_showcase_and_crm_host' => false,
            'app.url' => 'https://traklo.pro',
        ]);

        $request = Request::create('https://orientum.traklo.pro/login', 'GET');

        $this->assertSame('https://traklo.pro/', ShowcaseUrl::home($request));
    }

    public function test_same_host_flag_still_returns_absolute_apex(): void
    {
        config([
            'app.showcase_hosts' => ['traklo.pro'],
            'app.crm_domain' => 'traklo.pro',
            'app.same_showcase_and_crm_host' => true,
            'app.url' => 'https://traklo.pro',
        ]);

        $request = Request::create('https://demo.traklo.pro/login', 'GET');

        $this->assertSame('https://traklo.pro/', ShowcaseUrl::home($request));
        $this->assertNotSame('/', ShowcaseUrl::home($request));
    }
}
