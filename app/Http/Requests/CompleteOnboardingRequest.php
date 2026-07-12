<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'inn' => ['nullable', 'string', 'max:12'],
            'timezone' => ['required', 'string', 'max:64', Rule::in([
                'Europe/Moscow',
                'Europe/Samara',
                'Asia/Yekaterinburg',
                'Asia/Novosibirsk',
                'Asia/Vladivostok',
            ])],
            'sample_customer_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_name' => 'название компании',
            'inn' => 'ИНН',
            'timezone' => 'часовой пояс',
            'sample_customer_name' => 'пример заказчика',
        ];
    }
}
