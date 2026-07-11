<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\SaasFeatureCatalog;
use App\Support\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class PlatformDashboardController extends Controller
{
    public function index(): Response
    {
        TenantContext::bypass(true);

        $tenants = Tenant::query()->withCount('users')->get();

        $stats = [
            'tenants_total' => $tenants->count(),
            'users_total' => (int) $tenants->sum('users_count'),
            'by_status' => $tenants->groupBy('status')->map->count()->all(),
            'by_plan' => $tenants->groupBy(fn (Tenant $t): string => $t->planKey())->map->count()->all(),
            'trials_expiring_soon' => $tenants
                ->filter(fn (Tenant $t): bool => $t->status === 'trial' && $t->trial_ends_at !== null && $t->trial_ends_at->lte(now()->addDays(7)))
                ->map(fn (Tenant $t): array => [
                    'id' => $t->id,
                    'slug' => $t->slug,
                    'name' => $t->name,
                    'trial_ends_at' => $t->trial_ends_at?->toDateString(),
                ])
                ->values()
                ->all(),
        ];

        TenantContext::bypass(false);

        return Inertia::render('Platform/Dashboard/Index', [
            'stats' => $stats,
            'planOptions' => SaasFeatureCatalog::planSummaries(),
        ]);
    }
}
