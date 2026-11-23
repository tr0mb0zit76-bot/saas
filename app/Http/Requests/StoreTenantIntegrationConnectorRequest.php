<?php

namespace App\Http\Requests;

use App\Support\IntegrationConnectorRegistry;
use App\Support\RoleAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantIntegrationConnectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::userHasRoleName($this->user(), 'admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $selfServeKeys = array_column(IntegrationConnectorRegistry::catalogForTenant(), 'key');

        return [
            'adapter_key' => ['required', 'string', Rule::in($selfServeKeys)],
            'enabled' => ['sometimes', 'boolean'],
            'endpoints' => ['sometimes', 'array'],
            'endpoints.base_url' => ['nullable', 'string', 'max:500'],
            'mapping' => ['sometimes', 'array'],
            'secrets' => ['sometimes', 'array'],
            'secrets.token' => ['nullable', 'string', 'max:2000'],
            'secrets.username' => ['nullable', 'string', 'max:255'],
            'secrets.password' => ['nullable', 'string', 'max:255'],
            'secrets.client_id' => ['nullable', 'string', 'max:255'],
            'secrets.client_secret' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
