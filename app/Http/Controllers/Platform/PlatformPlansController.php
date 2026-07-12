<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdatePlatformPlanFeaturesRequest;
use App\Models\SubscriptionPlan;
use App\Services\Saas\TenantAuditLogger;
use App\Support\SaasFeatureCatalog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PlatformPlansController extends Controller
{
    public function __construct(
        private readonly TenantAuditLogger $auditLogger,
    ) {}
    public function index(): Response
    {
        $features = SaasFeatureCatalog::groupedFeatures();
        $plans = SaasFeatureCatalog::planSummaries();

        $matrix = array_map(function (array $feature) use ($plans): array {
            $row = [
                'key' => $feature['key'],
                'label' => $feature['label'],
                'group' => $feature['group'],
                'group_label' => $feature['group_label'],
                'plans' => [],
            ];

            foreach ($plans as $plan) {
                $row['plans'][$plan['key']] = in_array($feature['key'], $plan['features'], true);
            }

            return $row;
        }, $features);

        return Inertia::render('Platform/Plans/Index', [
            'plans' => $plans,
            'matrix' => $matrix,
            'groups' => SaasFeatureCatalog::groups(),
        ]);
    }

    public function edit(string $planKey): Response
    {
        $plan = $this->resolvePlan($planKey);
        $planFeatures = $plan->featuresList();

        $features = array_map(function (array $item) use ($planFeatures): array {
            return [
                ...$item,
                'enabled' => in_array($item['key'], $planFeatures, true),
            ];
        }, SaasFeatureCatalog::groupedFeatures());

        return Inertia::render('Platform/Plans/Edit', [
            'plan' => [
                'key' => $plan->key,
                'label' => $plan->label,
                'limits' => $plan->limitsMap(),
            ],
            'features' => $features,
        ]);
    }

    public function updateFeatures(UpdatePlatformPlanFeaturesRequest $request, string $planKey): RedirectResponse
    {
        $plan = $this->resolvePlan($planKey);

        $oldValues = [
            'features' => $plan->featuresList(),
            'limits' => $plan->limitsMap(),
        ];

        $enabledFeatures = [];

        foreach ($request->validated('features') as $key => $enabled) {
            if ($enabled) {
                $enabledFeatures[] = (string) $key;
            }
        }

        $limits = $request->validated('limits');

        $plan->update([
            'features' => array_values(array_unique($enabledFeatures)),
            'limits' => [
                'users' => $limits['users'] ?? null,
                'orders_per_month' => $limits['orders_per_month'] ?? null,
                'storage_mb' => $limits['storage_mb'] ?? null,
            ],
        ]);

        $this->auditLogger->log(
            null,
            $request->user()?->id,
            'plan.updated',
            'subscription_plan',
            $plan->id,
            $oldValues,
            [
                'plan_key' => $plan->key,
                'features' => $plan->fresh()->featuresList(),
                'limits' => $plan->fresh()->limitsMap(),
            ],
        );

        return to_route('platform.plans.edit', $plan->key)->with('flash', [
            'type' => 'success',
            'message' => "Тариф «{$plan->label}» обновлён (модули и лимиты).",
        ]);
    }

    private function resolvePlan(string $planKey): SubscriptionPlan
    {
        $planKey = strtolower(trim($planKey));

        $plan = SubscriptionPlan::query()->where('key', $planKey)->first();

        if ($plan === null) {
            throw new NotFoundHttpException('Тариф не найден.');
        }

        return $plan;
    }
}
