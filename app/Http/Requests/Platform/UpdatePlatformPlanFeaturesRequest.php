<?php

namespace App\Http\Requests\Platform;

use App\Support\SaasFeatureCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformPlanFeaturesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'features' => ['required', 'array'],
            'features.*' => ['boolean', Rule::in([true, false])],
            'limits' => ['required', 'array'],
            'limits.users' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'limits.orders_per_month' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'limits.storage_mb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $features = $this->input('features');

        if (is_array($features)) {
            $normalized = [];

            foreach ($features as $key => $value) {
                if (! in_array((string) $key, SaasFeatureCatalog::keys(), true)) {
                    continue;
                }

                $normalized[(string) $key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }

            $this->merge(['features' => $normalized]);
        }

        $limits = $this->input('limits');

        if (! is_array($limits)) {
            return;
        }

        $normalizedLimits = [];

        foreach (['users', 'orders_per_month', 'storage_mb'] as $key) {
            $value = $limits[$key] ?? null;

            if ($value === '' || $value === 'null') {
                $normalizedLimits[$key] = null;

                continue;
            }

            if ($value !== null) {
                $normalizedLimits[$key] = (int) $value;
            }
        }

        $this->merge(['limits' => $normalizedLimits]);
    }
}
