<?php

namespace Tests\Feature\Integrations;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegacyAaIntegrationsQuarantineTest extends TestCase
{
    #[Test]
    public function astral_and_onec_routes_are_404_when_legacy_flag_off(): void
    {
        config()->set('saas.legacy_aa_integrations_enabled', false);

        $this->postJson('/integrations/astral/epd/webhook', [])->assertNotFound();
        $this->getJson('/integrations/1c-fresh/etrn-journal')->assertNotFound();
        $this->postJson('/integrations/1c-fresh/etrn-status', [])->assertNotFound();
    }
}
