<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Support\SaasFeatureCatalog;
use Inertia\Inertia;
use Inertia\Response;

class PlatformPlansController extends Controller
{
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
}
