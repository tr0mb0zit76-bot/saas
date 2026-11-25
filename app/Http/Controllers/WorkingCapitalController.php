<?php

namespace App\Http\Controllers;

use App\Services\Finance\WorkingCapitalBalanceService;
use App\Services\Finance\WorkingCapitalRegisterService;
use App\Support\RoleAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ADR-025: Enterprise «Оборотный капитал» + «Баланс» (WC-lite).
 */
class WorkingCapitalController extends Controller
{
    public function register(Request $request, WorkingCapitalRegisterService $register): Response
    {
        $user = $request->user();
        abort_unless(RoleAccess::canViewPaymentSchedules($user), 403);

        return Inertia::render('Finance/WorkingCapital/Register', [
            'register' => $register->build($user),
        ]);
    }

    public function balance(Request $request, WorkingCapitalBalanceService $balance): Response
    {
        $user = $request->user();
        abort_unless(RoleAccess::canAccessManagementAccounting($user), 403);

        return Inertia::render('Finance/WorkingCapital/Balance', [
            'balance' => $balance->build($user),
        ]);
    }
}
