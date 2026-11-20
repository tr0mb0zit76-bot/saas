<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateThinCashOpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canAccessManagementAccounting($this->user());
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::id();

        return [
            'bank_account_id' => [
                'required',
                'integer',
                Rule::exists('management_bank_accounts', 'id')->when(
                    $tenantId !== null,
                    fn ($rule) => $rule->where('tenant_id', $tenantId),
                ),
            ],
            'opening_balance' => ['required', 'numeric'],
            'opening_balance_as_of' => ['nullable', 'date'],
            'opening_balance_source' => ['nullable', 'string', Rule::in(['manual', 'statement'])],
        ];
    }
}
