<?php

namespace Tests\Feature\Saas;

use App\Services\Saas\DemoSignupService;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Mail;
use Tests\SaasTestCase;

class DemoSignupTest extends SaasTestCase
{
    public function test_demo_signup_service_creates_trial_tenant(): void
    {
        Mail::fake();
        config(['saas.demo_signup_enabled' => true]);

        $email = 'demo-admin-'.uniqid().'@example.com';

        $result = app(DemoSignupService::class)->register(
            'Demo Forward LLC',
            'Demo Admin',
            $email,
        );

        TenantContext::bypass(true);

        $this->assertTrue($result['tenant']->isDemoTenant());
        $this->assertSame('trial', $result['tenant']->status);
        $this->assertDatabaseHas('tenants', ['slug' => $result['tenant']->slug, 'name' => 'Demo Forward LLC']);
        $this->assertSame(1, User::query()->withoutGlobalScopes()->where('tenant_id', $result['tenant']->id)->count());

        TenantContext::bypass(false);
    }

    public function test_demo_signup_form_is_available_when_enabled(): void
    {
        config(['saas.demo_signup_enabled' => true]);

        $this->get('/demo/signup')->assertOk();
    }

    public function test_demo_signup_form_is_hidden_when_disabled(): void
    {
        config(['saas.demo_signup_enabled' => false]);

        $this->get('/demo/signup')->assertNotFound();
    }
}
