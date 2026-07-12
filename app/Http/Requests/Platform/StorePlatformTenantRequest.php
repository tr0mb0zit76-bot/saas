<?php

namespace App\Http\Requests\Platform;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlatformTenantRequest extends FormRequest
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
            'slug' => [
                'required',
                'string',
                'max:63',
                'alpha_dash',
                Rule::unique('tenants', 'slug'),
                'not_in:platform,admin,www,api,mail',
            ],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(['active', 'trial', 'suspended'])],
            'plan' => ['required', 'string', Rule::in(SubscriptionPlan::planKeys())],
            'trial_ends_at' => ['nullable', 'date'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255'],
            'send_invite' => ['nullable', 'boolean'],
        ];
    }
}
