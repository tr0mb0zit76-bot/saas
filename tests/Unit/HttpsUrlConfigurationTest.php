<?php

namespace Tests\Unit;

use App\Http\Middleware\HandleInertiaRequests;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tighten\Ziggy\Ziggy;

class HttpsUrlConfigurationTest extends TestCase
{
    public function test_url_helper_uses_https_when_app_url_is_https(): void
    {
        config(['app.url' => 'https://crm.example.test']);

        URL::forceScheme('https');
        URL::forceRootUrl('https://crm.example.test');

        $this->assertSame('https://crm.example.test/documents', url('/documents'));
    }

    public function test_ziggy_base_url_uses_https_when_app_url_is_https(): void
    {
        config(['app.url' => 'https://crm.example.test']);

        URL::forceScheme('https');
        URL::forceRootUrl('https://crm.example.test');

        $ziggy = new Ziggy;

        $this->assertSame('https://crm.example.test', $ziggy->toArray()['url']);
    }

    public function test_inertia_url_resolver_upgrades_http_full_url_when_app_url_is_https(): void
    {
        config(['app.url' => 'https://crm.example.test']);

        $middleware = new HandleInertiaRequests;
        $resolver = $middleware->urlResolver();

        $request = Request::create(
            'http://crm.example.test/fleet/vehicles',
            'GET',
            [],
            [],
            [],
            ['HTTP_X_FORWARDED_PROTO' => 'https', 'HTTPS' => 'off'],
        );

        $this->assertSame('/fleet/vehicles', $resolver($request));
    }

    public function test_inertia_url_resolver_keeps_http_full_url_on_plain_http_request(): void
    {
        config(['app.url' => 'https://crm.example.test']);

        $middleware = new HandleInertiaRequests;
        $resolver = $middleware->urlResolver();

        $request = Request::create(
            'http://crm.example.test/login',
            'GET',
        );

        $this->assertSame('/login', $resolver($request));
    }

    public function test_url_helper_uses_http_when_request_is_plain_http_on_same_host(): void
    {
        config(['app.url' => 'https://crm.example.test']);

        $request = Request::create('http://crm.example.test/login', 'GET');
        $this->app->instance('request', $request);

        $provider = new AppServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'configureGeneratedUrls');
        $method->setAccessible(true);
        $method->invoke($provider, $request);

        $this->assertSame('http://crm.example.test/login', url('/login'));
    }

    public function test_lab_http_app_url_keeps_http_urls_when_forwarded_proto_is_https(): void
    {
        config(['app.url' => 'http://saas.local']);

        $request = Request::create(
            'http://saas.local/dashboard',
            'GET',
            [],
            [],
            [],
            ['HTTP_X_FORWARDED_PROTO' => 'https', 'HTTPS' => 'off'],
        );
        $this->app->instance('request', $request);

        $provider = new AppServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'configureGeneratedUrls');
        $method->setAccessible(true);
        $method->invoke($provider, $request);

        $this->assertSame('http://saas.local/profile/ui-preferences', route('profile.ui-preferences'));
    }

    public function test_lab_http_app_url_disables_secure_session_cookies_even_with_forwarded_https(): void
    {
        config(['app.url' => 'http://saas.local']);

        $provider = new AppServiceProvider($this->app);
        $boot = new \ReflectionMethod($provider, 'boot');
        $boot->setAccessible(true);
        $boot->invoke($provider);

        $this->assertFalse(config('session.secure'));
    }

    public function test_platform_host_uses_http_root_on_plain_http_lab_request(): void
    {
        config([
            'app.url' => 'http://saas.local',
            'app.platform_domain' => 'platform.saas.local',
        ]);

        $request = Request::create('http://platform.saas.local/login', 'GET');
        $this->app->instance('request', $request);

        $provider = new AppServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'configureGeneratedUrls');
        $method->setAccessible(true);
        $method->invoke($provider, $request);

        $this->assertSame('http://platform.saas.local/login', url('/login'));
    }
}
