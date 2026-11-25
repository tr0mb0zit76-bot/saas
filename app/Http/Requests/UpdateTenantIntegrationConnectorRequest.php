<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantIntegrationConnectorRequest extends FormRequest
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
        return [
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
