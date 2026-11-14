<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Weight field on a cargo line is either per-place (legacy default) or total for the line.
 * Customer cabinet «Просчёт» always means total («Общий вес»).
 */
final class CargoWeightSemantics
{
    public static function isTotalWeight(mixed $source): bool
    {
        if (is_bool($source)) {
            return $source;
        }

        if (is_object($source)) {
            if (isset($source->weight_is_total)) {
                return self::truthy($source->weight_is_total);
            }

            $metadata = is_array($source->metadata ?? null) ? $source->metadata : [];
            if (array_key_exists('weight_is_total', $metadata)) {
                return self::truthy($metadata['weight_is_total']);
            }

            $ati = is_array($source->ati_cargo_payload ?? null) ? $source->ati_cargo_payload : [];
            if (array_key_exists('weight_is_total', $ati)) {
                return self::truthy($ati['weight_is_total']);
            }

            return false;
        }

        if (! is_array($source)) {
            return false;
        }

        if (array_key_exists('weight_is_total', $source)) {
            return self::truthy($source['weight_is_total']);
        }

        $metadata = is_array($source['metadata'] ?? null) ? $source['metadata'] : [];
        if (array_key_exists('weight_is_total', $metadata)) {
            return self::truthy($metadata['weight_is_total']);
        }

        $ati = is_array($source['ati_cargo_payload'] ?? null) ? $source['ati_cargo_payload'] : [];
        if (array_key_exists('weight_is_total', $ati)) {
            return self::truthy($ati['weight_is_total']);
        }

        return false;
    }

    /**
     * Line total in kg from entered weight_value (+ unit) and package_count.
     */
    public static function lineTotalKg(
        mixed $weightValue,
        string $weightUnit = 'kg',
        mixed $packageCount = null,
        bool $weightIsTotal = false,
    ): float {
        $kg = self::weightToKg($weightValue, $weightUnit);
        if ($kg <= 0.0) {
            return 0.0;
        }

        if ($weightIsTotal) {
            return $kg;
        }

        return $kg * self::packageFactor($packageCount);
    }

    public static function weightToKg(mixed $weightValue, string $weightUnit = 'kg'): float
    {
        if ($weightValue === null || $weightValue === '' || ! is_numeric($weightValue)) {
            return 0.0;
        }

        $value = (float) $weightValue;
        if (! is_finite($value) || $value <= 0.0) {
            return 0.0;
        }

        return $weightUnit === 't' ? $value * 1000.0 : $value;
    }

    public static function packageFactor(mixed $packageCount): int
    {
        if ($packageCount === null || $packageCount === '' || ! is_numeric($packageCount)) {
            return 1;
        }

        $count = (float) $packageCount;
        if (! is_finite($count) || $count <= 0.0) {
            return 1;
        }

        return max(1, (int) $count);
    }

    private static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value !== 0.0;
        }

        if (! is_string($value)) {
            return false;
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
