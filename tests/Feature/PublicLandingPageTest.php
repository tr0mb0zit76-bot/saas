<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicLandingPageTest extends TestCase
{
    private function showcaseUrl(string $path = '/'): string
    {
        $host = config('app.showcase_hosts')[0] ?? 'v5.local';

        return 'http://'.$host.$path;
    }

    public function test_guest_can_open_public_landing_page(): void
    {
        config(['showcase.mode' => 'legacy']);

        $response = $this->get($this->showcaseUrl('/'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('canLogin', Route::has('login'))
            ->where('canRegister', Route::has('register'))
            ->has('publicSite.texts')
        );
    }

    public function test_guest_can_open_traklo_pro_landing_page(): void
    {
        config(['showcase.mode' => 'traklo_pro']);

        $this->get($this->showcaseUrl('/'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/TrakloLanding')
                ->where('canLogin', Route::has('login'))
                ->has('texts.brand')
                ->has('plans')
            );
    }

    public function test_traklo_pro_landing_soft_gates_when_coming_soon(): void
    {
        config([
            'showcase.mode' => 'traklo_pro',
            'showcase.coming_soon' => true,
            'saas.demo_signup_enabled' => true,
        ]);

        $this->get($this->showcaseUrl('/'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/TrakloLanding')
                ->where('comingSoon', true)
                ->where('demoSignupUrl', null)
                ->where('texts.coming_soon_banner', 'Мы работаем над созданием Traklo Pro')
            );
    }

    public function test_traklo_pro_landing_exposes_demo_signup_when_open(): void
    {
        config([
            'showcase.mode' => 'traklo_pro',
            'showcase.coming_soon' => false,
            'saas.demo_signup_enabled' => true,
            'app.crm_domain' => 'crm.test',
        ]);

        $this->get($this->showcaseUrl('/'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/TrakloLanding')
                ->where('comingSoon', false)
                ->where('demoSignupUrl', 'http://crm.test/demo/signup')
            );
    }

    public function test_guest_can_open_public_secondary_pages(): void
    {
        $this->get($this->showcaseUrl('/about'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Public/About'));

        $this->get($this->showcaseUrl('/services'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Public/Services'));

        $this->get($this->showcaseUrl('/cases'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Public/Cases'));

        $this->get($this->showcaseUrl('/contacts'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Public/Contacts'));

        $this->get($this->showcaseUrl('/sla'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Sla')
                ->has('publicSite.traklo_apk_url')
            );
    }

    public function test_public_pages_prefer_public_locale_file_when_available(): void
    {
        $localeDirectory = public_path('locales');
        $localePath = $localeDirectory.'/ru.json';

        File::ensureDirectoryExists($localeDirectory);
        File::put($localePath, json_encode([
            'welcome_title' => 'Тестовый заголовок',
            'footer_name' => 'Тестовый футер',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        try {
            $this->get($this->showcaseUrl('/'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('publicSite.texts.welcome_title', 'Тестовый заголовок')
                    ->where('publicSite.texts.footer_name', 'Тестовый футер')
                );
        } finally {
            File::delete($localePath);
        }
    }

    public function test_traklo_pro_landing_uses_relative_login_on_single_host_lab(): void
    {
        config([
            'showcase.mode' => 'traklo_pro',
            'app.crm_domain' => 'saas.local',
            'app.showcase_hosts' => ['saas.local'],
            'app.same_showcase_and_crm_host' => true,
            'app.platform_domain' => 'platform.saas.local',
        ]);

        $this->get('http://saas.local/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/TrakloLanding')
                ->where('crmLoginUrl', '/login')
                ->where('publicSite.crm_login_url', '/login')
            );
    }

    public function test_traklo_pro_landing_points_login_cta_to_platform_on_production_apex(): void
    {
        // Keep same_showcase true so apex `/` stays a Showcase document path under
        // production IdentifyTenant; production alone flips the CTA to platform login.
        config([
            'showcase.mode' => 'traklo_pro',
            'showcase.coming_soon' => true,
            'app.same_showcase_and_crm_host' => true,
            'app.platform_domain' => 'platform.traklo.pro',
            'app.url' => 'https://traklo.pro',
        ]);

        $previous = $this->app->make('env');
        $this->app->instance('env', 'production');

        try {
            $this->get($this->showcaseUrl('/'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Public/TrakloLanding')
                    ->where('comingSoon', true)
                    ->where('crmLoginUrl', 'https://platform.traklo.pro/login')
                    ->where('publicSite.crm_login_url', 'https://platform.traklo.pro/login')
                );
        } finally {
            $this->app->instance('env', $previous);
        }
    }

    public function test_apex_login_redirects_to_platform_not_404(): void
    {
        $apex = config('app.showcase_hosts')[0] ?? 'saas.local';

        config([
            'app.platform_domain' => 'platform.'.$apex,
            'app.same_showcase_and_crm_host' => true,
        ]);

        $previous = $this->app->make('env');
        $this->app->instance('env', 'production');

        try {
            $this->get($this->showcaseUrl('/login'))
                ->assertRedirect('http://platform.'.$apex.'/login');
        } finally {
            $this->app->instance('env', $previous);
        }
    }

    public function test_authenticated_user_is_redirected_from_root_to_dashboard(): void
    {
        $user = new User;
        $user->id = 1;
        $user->tenant_id = 1;
        $user->name = 'Admin User';
        $user->email = 'admin@example.com';
        $user->exists = true;

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertTrue(
            str_contains($location, '/dashboard') || str_contains($location, '/onboarding'),
            "Expected cabinet redirect, got {$location}"
        );
    }
}
