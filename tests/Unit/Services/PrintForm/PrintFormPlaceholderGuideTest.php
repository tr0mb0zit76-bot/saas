<?php

namespace Tests\Unit\Services\PrintForm;

use App\Services\PrintForm\PrintFormPlaceholderGuide;
use App\Services\PrintFormVariableCatalog;
use App\Support\PrintFormPlaceholderPathResolver;
use Tests\TestCase;

class PrintFormPlaceholderGuideTest extends TestCase
{
    public function test_guide_contains_entity_paths_legacy_aliases_and_special_macros(): void
    {
        $guide = new PrintFormPlaceholderGuide(
            new PrintFormVariableCatalog,
            new PrintFormPlaceholderPathResolver,
        );

        $payload = $guide->toArray();

        $this->assertNotSame('', $payload['intro']);
        $this->assertNotEmpty($payload['usage_steps']);
        $this->assertCount(2, $payload['entity_types']);
        $this->assertSame('order', $payload['entity_types'][0]['key']);
        $this->assertNotEmpty($payload['entity_types'][0]['groups']);
        $this->assertNotEmpty($payload['legacy_aliases']);
        $this->assertNotEmpty($payload['special_macros']);

        $legacyItems = $payload['legacy_aliases'][0]['items'] ?? [];
        $this->assertNotEmpty($legacyItems);
        $this->assertArrayHasKey('macro', $legacyItems[0]);
        $this->assertArrayHasKey('path', $legacyItems[0]);
    }
}
