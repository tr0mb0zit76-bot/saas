<?php

namespace App\Http\Requests\Platform;

use App\Services\ContentPacks\SalesBookContentPackInstaller;
use App\Support\IntegrationConnectorRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformTenantOpsRequest extends FormRequest
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
            'document_verification' => ['sometimes', 'boolean'],
            'install_pack' => ['sometimes', 'nullable', 'string', Rule::in(SalesBookContentPackInstaller::knownPacks())],
            'reset_pack' => ['sometimes', 'boolean'],
            'connector' => ['sometimes', 'array'],
            'connector.adapter_key' => ['required_with:connector', 'string', Rule::in(IntegrationConnectorRegistry::adapterKeys())],
            'connector.enabled' => ['sometimes', 'boolean'],
            'connector.id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'connector.username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'connector.password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'connector.base_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'connector.clone_aa_template' => ['sometimes', 'boolean'],
        ];
    }
}
