<?php

namespace App\Http\Requests;

use App\Support\CrmAppearance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUiPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $colorRules = [];
        foreach (CrmAppearance::colorKeys() as $key) {
            $colorRules["theme_colors.{$key}"] = ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        }

        return [
            'button_radius' => ['sometimes', Rule::in([
                CrmAppearance::BUTTON_RADIUS_SHARP,
                CrmAppearance::BUTTON_RADIUS_ROUNDED,
            ])],
            'primary_accent' => ['sometimes', Rule::in([
                CrmAppearance::PRIMARY_ACCENT_EMERALD,
                CrmAppearance::PRIMARY_ACCENT_SKY,
            ])],
            'tab_style' => ['sometimes', Rule::in([
                CrmAppearance::TAB_STYLE_FILLED,
                CrmAppearance::TAB_STYLE_UNDERLINE,
            ])],
            'workspace_skin' => ['sometimes', Rule::in(CrmAppearance::workspaceSkinValues())],
            'nav_icon_style' => ['sometimes', Rule::in([
                CrmAppearance::NAV_ICON_STYLE_MONOCHROME,
                CrmAppearance::NAV_ICON_STYLE_SEMANTIC,
                CrmAppearance::NAV_ICON_STYLE_TINTED,
            ])],
            'ag_grid_density' => ['sometimes', Rule::in(['compact', 'normal', 'comfortable'])],
            'theme_colors' => ['sometimes', 'nullable', 'array'],
            ...$colorRules,
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->exists('theme_colors')) {
                return;
            }

            $colors = $this->input('theme_colors');
            if ($colors === null) {
                return;
            }

            if (! is_array($colors)) {
                $validator->errors()->add('theme_colors', 'Некорректная палитра.');
            }
        });
    }
}
