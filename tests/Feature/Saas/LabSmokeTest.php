<?php

namespace Tests\Feature\Saas;

use Tests\SaasTestCase;

class LabSmokeTest extends SaasTestCase
{
    public function test_login_route_is_available(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }
}
