<?php

namespace Tests\Unit;

use App\Support\CargoWeightSemantics;
use App\Support\LeadCargoItemPayloadNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CargoWeightSemanticsTest extends TestCase
{
    #[Test]
    public function total_weight_14_4_tons_with_16_places_does_not_multiply(): void
    {
        $totalKg = CargoWeightSemantics::lineTotalKg(
            weightValue: 14.4,
            weightUnit: 't',
            packageCount: 16,
            weightIsTotal: true,
        );

        $this->assertEqualsWithDelta(14400.0, $totalKg, 0.01);
        $this->assertNotEqualsWithDelta(230400.0, $totalKg, 0.01);
    }

    #[Test]
    public function per_place_weight_still_multiplies_by_places(): void
    {
        $totalKg = CargoWeightSemantics::lineTotalKg(
            weightValue: 14.4,
            weightUnit: 't',
            packageCount: 16,
            weightIsTotal: false,
        );

        $this->assertEqualsWithDelta(230400.0, $totalKg, 0.01);
    }

    #[Test]
    public function lead_normalizer_round_trips_weight_is_total_flag(): void
    {
        $stored = LeadCargoItemPayloadNormalizer::toDatabase([
            'name' => 'Оборудование',
            'weight_value' => 14.4,
            'weight_unit' => 't',
            'package_count' => 16,
            'weight_is_total' => true,
        ]);

        $this->assertTrue(($stored['metadata']['weight_is_total'] ?? false) === true);
        $this->assertEqualsWithDelta(14400.0, (float) $stored['weight_kg'], 0.01);
        $this->assertSame(16, $stored['package_count']);

        $totalKg = CargoWeightSemantics::lineTotalKg(
            weightValue: $stored['metadata']['weight_value'],
            weightUnit: $stored['metadata']['weight_unit'],
            packageCount: $stored['package_count'],
            weightIsTotal: CargoWeightSemantics::isTotalWeight($stored),
        );

        $this->assertEqualsWithDelta(14400.0, $totalKg, 0.01);
    }
}
