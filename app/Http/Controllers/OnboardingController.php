<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteOnboardingRequest;
use App\Services\Saas\TenantOnboardingWizardService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(): Response|RedirectResponse
    {
        $tenant = TenantContext::get();

        if ($tenant === null || $tenant->onboardingCompleted()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Onboarding/Index', [
            'companyName' => $tenant->name,
            'defaultTimezone' => (string) config('app.timezone', 'Europe/Moscow'),
            'timezones' => [
                'Europe/Moscow',
                'Europe/Samara',
                'Asia/Yekaterinburg',
                'Asia/Novosibirsk',
                'Asia/Vladivostok',
            ],
        ]);
    }

    public function store(CompleteOnboardingRequest $request, TenantOnboardingWizardService $wizard): RedirectResponse
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        abort_if($tenant === null || $user === null, 403);

        $wizard->complete($tenant, $user, $request->validated());

        return redirect()->route('dashboard')->with('flash', [
            'type' => 'success',
            'message' => 'Настройка завершена. Добро пожаловать в Traklo Pro!',
        ]);
    }
}
