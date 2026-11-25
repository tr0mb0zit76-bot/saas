<?php

namespace App\Support;

use App\Services\PrintFormVariableCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validation for template variable_mappings.*.source_path (ADR-016 allowlist).
 */
final class PrintFormCatalogPathRule
{
    /**
     * @return list<ValidationRule|string>
     */
    public static function sourcePath(): array
    {
        $paths = array_keys(app(PrintFormVariableCatalog::class)->pathLabelIndex());

        return [
            'nullable',
            'string',
            'max:255',
            Rule::in(['', ...$paths]),
        ];
    }
}
