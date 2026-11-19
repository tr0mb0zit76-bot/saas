<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Maps OrderIntakeSchema / TransportTextIntake extracted payload into the
 * customer cabinet «Просчёт» form draft (fill-only, no Lead create).
 */
final class CustomerCargoRequestDraftMapper
{
    /**
     * @param  array<string, mixed>  $extracted
     * @return array{
     *     loading_location: string,
     *     unloading_location: string,
     *     loading_date: string,
     *     unloading_date: string,
     *     transport_type: string,
     *     cargo_name: string,
     *     weight_value: string,
     *     weight_unit: string,
     *     volume_m3: string,
     *     length_m: string,
     *     width_m: string,
     *     height_m: string,
     *     package_count: string,
     *     package_type: string,
     *     dangerous_goods: bool,
     *     dangerous_class: string,
     *     cargo_description: string,
     *     comment: string
     * }
     */
    public static function fromExtracted(array $extracted, ?string $notesFallback = null): array
    {
        $route = is_array($extracted['route'] ?? null) ? $extracted['route'] : [];
        $loading = is_array($route['loading'] ?? null) ? $route['loading'] : [];
        $unloading = is_array($route['unloading'] ?? null) ? $route['unloading'] : [];
        $cargo = is_array($extracted['cargo'] ?? null) ? $extracted['cargo'] : [];

        $weightKg = self::nullableFloat($cargo['weight_kg'] ?? null);
        $weightValue = '';
        $weightUnit = 't';
        if ($weightKg !== null) {
            if ($weightKg >= 1000 || fmod($weightKg, 1000.0) === 0.0) {
                $tons = $weightKg / 1000;
                $weightValue = self::formatNumber($tons);
                $weightUnit = 't';
            } else {
                $weightValue = self::formatNumber($weightKg);
                $weightUnit = 'kg';
            }
        }

        $description = self::nullableString($cargo['description'] ?? null) ?? '';
        $notes = self::nullableString($extracted['notes'] ?? null)
            ?? self::nullableString($notesFallback)
            ?? '';

        return [
            'loading_location' => self::nullableString($loading['address'] ?? null) ?? '',
            'unloading_location' => self::nullableString($unloading['address'] ?? null) ?? '',
            'loading_date' => OrderAgentLexicon::normalizeDateValue($loading['planned_date'] ?? null) ?? '',
            'unloading_date' => OrderAgentLexicon::normalizeDateValue($unloading['planned_date'] ?? null) ?? '',
            'transport_type' => '',
            'cargo_name' => self::nullableString($cargo['name'] ?? null) ?? '',
            'weight_value' => $weightValue,
            'weight_unit' => $weightUnit,
            'volume_m3' => self::formatOptionalNumber($cargo['volume_m3'] ?? null),
            'length_m' => self::formatOptionalNumber($cargo['length_m'] ?? null),
            'width_m' => self::formatOptionalNumber($cargo['width_m'] ?? null),
            'height_m' => self::formatOptionalNumber($cargo['height_m'] ?? null),
            'package_count' => isset($cargo['package_count']) && is_numeric($cargo['package_count'])
                ? (string) (int) $cargo['package_count']
                : '',
            'package_type' => self::nullableString($cargo['package_type'] ?? null) ?? '',
            'dangerous_goods' => (bool) ($cargo['dangerous_goods'] ?? false),
            'dangerous_class' => self::nullableString($cargo['dangerous_class'] ?? null) ?? '',
            'cargo_description' => $description,
            'comment' => $notes,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private static function formatNumber(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    private static function formatOptionalNumber(mixed $value): string
    {
        $float = self::nullableFloat($value);

        return $float === null ? '' : self::formatNumber($float);
    }
}
