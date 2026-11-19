<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_mobile_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/mobile/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_authenticate_using_the_mobile_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/mobile/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('mobile.messenger.app', absolute: false));
    }

    public function test_mobile_messenger_guests_are_redirected_to_mobile_login(): void
    {
        $response = $this->get('/mobile/messenger');

        $response->assertRedirect('/mobile/login');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_lab_http_logout_succeeds_with_forwarded_https_header(): void
    {
        config(['app.url' => 'http://saas.local']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeader('X-Forwarded-Proto', 'https')
            ->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_lab_http_session_cookie_is_not_secure_with_forwarded_https(): void
    {
        config(['app.url' => 'http://saas.local']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('/dashboard');

        $response->assertOk();

        $sessionCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => str_contains($cookie->getName(), 'session'));

        $this->assertNotNull($sessionCookie);
        $this->assertFalse($sessionCookie->isSecure());
    }
}
