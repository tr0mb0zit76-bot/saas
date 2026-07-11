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
        ];
    }

    protected function prepareForValidation(): void
    {
        $features = $this->input('features');

        if (! is_array($features)) {
            return;
        }

        $normalized = [];

        foreach ($features as $key => $value) {
            if (! in_array((string) $key, SaasFeatureCatalog::keys(), true)) {
                continue;
            }

            $normalized[(string) $key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        $this->merge(['features' => $normalized]);
    }
}
