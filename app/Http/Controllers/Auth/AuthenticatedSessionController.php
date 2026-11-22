<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Tenant;
use App\Services\Mobile\MobileDeviceService;
use App\Support\TenantContext;
use App\Support\TenantHost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /** @var list<string> */
    private const RESERVED_TENANT_SLUGS = [
        'www', 'platform', 'mail', 'api', 'app', 'admin', 'static', 'assets', 'cdn', 'status',
    ];

    public function __construct(
        private MobileDeviceService $mobileDeviceService,
    ) {}

    /**
     * Display the login view (tenant CRM) or apex tenant picker (no tenant on host).
     */
    public function create(): Response
    {
        if (TenantContext::get() === null) {
            return Inertia::render('Public/TenantLogin', [
                'crmDomain' => strtolower(trim((string) config('app.crm_domain', ''))),
                'status' => session('status'),
            ]);
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Apex: resolve company slug → redirect to {slug}.{CRM_DOMAIN}/login.
     */
    public function redirectToTenant(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ], [
            'slug.required' => 'Укажите код компании.',
            'slug.regex' => 'Код компании: латиница, цифры и дефис.',
        ]);

        $slug = strtolower(trim($validated['slug']));

        if (in_array($slug, self::RESERVED_TENANT_SLUGS, true)) {
            throw ValidationException::withMessages([
                'slug' => 'Такой код компании недоступен.',
            ]);
        }

        $tenant = TenantContext::runWithoutScope(fn () => Tenant::query()
            ->where('slug', $slug)
            ->whereIn('status', ['active', 'trial', 'suspended'])
            ->first());

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'slug' => 'Компания не найдена. Проверьте код или ссылку от администратора.',
            ]);
        }

        return redirect()->away(TenantHost::url($tenant->slug, '/login'));
    }

    public function createMobile(): Response
    {
        return Inertia::render('Mobile/Login', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function storeMobile(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $deviceKey = $request->string('device_key')->toString();
        $user = $request->user();

        if ($deviceKey !== '' && $user !== null && $this->mobileDeviceService->deviceNeedsPinSetup($user, $deviceKey)) {
            return redirect()->route('mobile.pin-setup', ['device_key' => $deviceKey]);
        }

        return redirect()->intended(route('mobile.messenger.app', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
