<?php

namespace App\Http\Controllers;

use App\Http\Requests\DemoSignupRequest;
use App\Services\Saas\DemoSignupService;
use App\Support\TenantHost;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DemoSignupController extends Controller
{
    public function create(): Response
    {
        if (! config('saas.demo_signup_enabled', false)) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('Public/DemoSignup', [
            'trialDays' => (int) config('saas.trial_days', 14),
        ]);
    }

    public function store(DemoSignupRequest $request, DemoSignupService $signup): RedirectResponse
    {
        $result = $signup->register(
            $request->string('company_name')->toString(),
            $request->string('admin_name')->toString(),
            $request->string('admin_email')->toString(),
        );

        return redirect(TenantHost::url($result['tenant']->slug, '/login'))->with('flash', [
            'type' => 'success',
            'message' => 'Демо-доступ создан. Проверьте email '.$result['user']->email.' — там временный пароль для входа.',
        ]);
    }
}
