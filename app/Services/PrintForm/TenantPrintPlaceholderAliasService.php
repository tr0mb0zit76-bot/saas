<?php

namespace App\Services\PrintForm;

use App\Models\Tenant;
use App\Services\PrintFormVariableCatalog;
use App\Support\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Tenant-owned DOCX macro aliases → platform catalog paths (refinement backlog WS-2, variant B).
 */
class TenantPrintPlaceholderAliasService
{
    public const SETTINGS_KEY = 'print_placeholder_aliases';

    public function __construct(
        private readonly PrintFormVariableCatalog $variableCatalog,
    ) {}

    /**
     * @return list<array{macro: string, path: string, label: string}>
     */
    public function listForTenant(?Tenant $tenant = null): array
    {
        $tenant ??= TenantContext::get();
        if ($tenant === null) {
            return [];
        }

        $raw = data_get($tenant->settings, self::SETTINGS_KEY, []);
        if (! is_array($raw)) {
            return [];
        }

        $pathLabels = $this->variableCatalog->pathLabelIndex();
        $out = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $macro = $this->normalizeMacro((string) ($row['macro'] ?? ''));
            $path = trim((string) ($row['path'] ?? ''));
            if ($macro === '' || $path === '' || ! isset($pathLabels[$path])) {
                continue;
            }
            $out[] = [
                'macro' => $macro,
                'path' => $path,
                'label' => (string) $pathLabels[$path],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string> macro => path
     */
    public function mapForCurrentTenant(): array
    {
        $map = [];
        foreach ($this->listForTenant() as $row) {
            $map[$row['macro']] = $row['path'];
        }

        return $map;
    }

    /**
     * @param  list<array{macro?: string, path?: string}>  $aliases
     * @return list<array{macro: string, path: string}>
     */
    public function normalizeAndValidate(array $aliases): array
    {
        $allowedPaths = $this->variableCatalog->pathLabelIndex();
        $normalized = [];
        $seen = [];

        foreach ($aliases as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $macro = $this->normalizeMacro((string) ($row['macro'] ?? ''));
            $path = trim((string) ($row['path'] ?? ''));

            if ($macro === '' && $path === '') {
                continue;
            }

            if ($macro === '' || ! preg_match('/^[\p{L}_][\p{L}\p{N}_.\-\/]*$/u', $macro)) {
                throw ValidationException::withMessages([
                    "aliases.{$index}.macro" => 'Некорректное имя плейсхолдера. Используйте буквы, цифры, _ . - /',
                ]);
            }

            if ($path === '' || ! isset($allowedPaths[$path])) {
                throw ValidationException::withMessages([
                    "aliases.{$index}.path" => 'Выберите сопоставление из справочника платформы.',
                ]);
            }

            if (isset($seen[$macro])) {
                throw ValidationException::withMessages([
                    "aliases.{$index}.macro" => "Плейсхолдер «{$macro}» уже добавлен.",
                ]);
            }

            $seen[$macro] = true;
            $normalized[] = [
                'macro' => $macro,
                'path' => $path,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{macro?: string, path?: string}>  $aliases
     * @return list<array{macro: string, path: string, label: string}>
     */
    public function saveForTenant(Tenant $tenant, array $aliases): array
    {
        $normalized = $this->normalizeAndValidate($aliases);
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings[self::SETTINGS_KEY] = $normalized;
        $tenant->settings = $settings;
        $tenant->save();

        return $this->listForTenant($tenant->fresh());
    }

    public function normalizeMacro(string $macro): string
    {
        $macro = trim($macro);
        $macro = preg_replace('/^\$\{|\}$/', '', $macro) ?? $macro;
        $macro = preg_replace('/^\{\{|\}\}$/', '', $macro) ?? $macro;

        return trim($macro);
    }
}
