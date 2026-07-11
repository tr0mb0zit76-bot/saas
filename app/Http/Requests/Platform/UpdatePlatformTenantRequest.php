<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformTenantRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(['active', 'trial', 'suspended'])],
            'plan' => ['required', 'string', Rule::in(array_keys(config('saas-plans.plans', [])))],
            'trial_ends_at' => ['nullable', 'date'],
        ];
    }
}
