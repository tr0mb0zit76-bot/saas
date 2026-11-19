<?php

namespace App\Http\Requests;

use App\Models\BudgetOpexArticle;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetOpexArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canAccessBudgeting($this->user());
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        $costType = $this->input('cost_type', BudgetOpexArticle::COST_FIXED_MONTHLY);

        return [
            'name' => ['required', 'string', 'max:255'],
            'cost_type' => ['required', 'string', Rule::in([
                BudgetOpexArticle::COST_FIXED_MONTHLY,
                BudgetOpexArticle::COST_PERCENT_OF_MARGIN,
            ])],
            'amount_basis' => ['nullable', 'string', Rule::in([
                BudgetOpexArticle::AMOUNT_BASIS_TOTAL,
                BudgetOpexArticle::AMOUNT_BASIS_PER_HEAD,
            ])],
            'amount_monthly' => [
                Rule::requiredIf($costType === BudgetOpexArticle::COST_FIXED_MONTHLY),
                'nullable',
                'numeric',
                'min:0',
            ],
            'percent_of_margin' => [
                Rule::requiredIf($costType === BudgetOpexArticle::COST_PERCENT_OF_MARGIN),
                'nullable',
                'numeric',
                'min:0',
                'max:99',
            ],
            'ramp_months' => ['nullable', 'integer', 'min:1', 'max:36'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('amount_basis')) {
            $this->merge(['amount_basis' => BudgetOpexArticle::AMOUNT_BASIS_TOTAL]);
        }
    }
}
