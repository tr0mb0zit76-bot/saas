<?php

use App\Http\Controllers\Platform\PlatformAuditLogController;
use App\Http\Controllers\Platform\PlatformAuthenticatedSessionController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\PlatformPlansController;
use App\Http\Controllers\Platform\PlatformTenantController;
use App\Http\Controllers\Platform\PlatformTenantInvoiceController;
use App\Support\PlatformHost;
use Illuminate\Support\Facades\Route;

$platformDomain = PlatformHost::domain();

if ($platformDomain === '') {
    return;
}

Route::domain($platformDomain)->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [PlatformAuthenticatedSessionController::class, 'create'])
            ->name('platform.login');

        Route::post('login', [PlatformAuthenticatedSessionController::class, 'store'])
            ->name('platform.login.store');
    });

    Route::middleware(['auth', 'verified', 'platform.admin'])->group(function (): void {
        Route::post('logout', [PlatformAuthenticatedSessionController::class, 'destroy'])
            ->name('platform.logout');

        Route::get('/', [PlatformDashboardController::class, 'index'])->name('platform.dashboard');
        Route::get('/plans', [PlatformPlansController::class, 'index'])->name('platform.plans.index');
        Route::get('/plans/{planKey}/features', [PlatformPlansController::class, 'edit'])->name('platform.plans.edit');
        Route::patch('/plans/{planKey}/features', [PlatformPlansController::class, 'updateFeatures'])->name('platform.plans.features.update');
        Route::get('/tenants', [PlatformTenantController::class, 'index'])->name('platform.tenants.index');
        Route::post('/tenants', [PlatformTenantController::class, 'store'])->name('platform.tenants.store');
        Route::patch('/tenants/{tenant}', [PlatformTenantController::class, 'update'])->name('platform.tenants.update');
        Route::get('/tenants/{tenant}/features', [PlatformTenantController::class, 'features'])->name('platform.tenants.features');
        Route::patch('/tenants/{tenant}/features', [PlatformTenantController::class, 'updateFeatures'])->name('platform.tenants.features.update');
        Route::post('/tenants/{tenant}/mark-paid', [PlatformTenantController::class, 'markPaid'])->name('platform.tenants.mark-paid');
        Route::get('/tenants/{tenant}/invoices/{invoice}/pdf', [PlatformTenantInvoiceController::class, 'pdf'])->name('platform.tenants.invoices.pdf');
        Route::get('/audit', [PlatformAuditLogController::class, 'index'])->name('platform.audit.index');
    });
});
