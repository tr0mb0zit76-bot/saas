<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorContact;
use App\Models\Role;
use App\Models\User;
use App\Services\ExternalUsers\ExternalUserProvisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExternalUserProvisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_invite_for_carrier_contact(): void
    {
        if (! Schema::hasTable('external_user_invites')) {
            $this->markTestSkipped('external_user_invites migration is not applied.');
        }

        $staff = User::factory()->create();
        $carrierRole = Role::query()->where('name', 'counterparty_carrier')->first();
        $this->assertNotNull($carrierRole);

        $contractor = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'ООО Перевоз',
        ]);
        $contact = ContractorContact::query()->create([
            'contractor_id' => $contractor->id,
            'full_name' => 'Иван Перевозчик',
            'email' => 'carrier@example.test',
            'phone' => '+79001112233',
            'is_primary' => true,
        ]);

        $payload = app(ExternalUserProvisionService::class)->provisionInvite(
            $contractor,
            $contact,
            $staff,
        );

        $this->assertTrue($payload['created']);
        $this->assertStringContainsString('/external/invite/', $payload['url']);
        $this->assertStringNotContainsString('renew=1', $payload['url']);

        $this->assertDatabaseHas('users', [
            'email' => 'carrier@example.test',
            'is_external' => true,
            'contractor_id' => $contractor->id,
            'contractor_contact_id' => $contact->id,
            'external_party' => 'carrier',
        ]);

        // Re-issue: staff «Новая ссылка» — renew so QA can reset password without burning only-login redirect.
        $reissue = app(ExternalUserProvisionService::class)->provisionInvite(
            $contractor,
            $contact,
            $staff,
        );
        $this->assertFalse($reissue['created']);
        $this->assertStringContainsString('renew=1', $reissue['url']);
    }

    public function test_staff_can_create_invite_for_customer_contact(): void
    {
        if (! Schema::hasTable('external_user_invites')) {
            $this->markTestSkipped('external_user_invites migration is not applied.');
        }

        $staff = User::factory()->create();
        $customerRole = Role::query()->where('name', 'counterparty_customer')->first();
        $this->assertNotNull($customerRole);

        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Заказчик',
        ]);
        $contact = ContractorContact::query()->create([
            'contractor_id' => $contractor->id,
            'full_name' => 'Василий',
            'email' => 'customer-cabinet@example.test',
            'phone' => '+79005554433',
            'is_primary' => true,
        ]);

        $payload = app(ExternalUserProvisionService::class)->provisionInvite(
            $contractor,
            $contact,
            $staff,
        );

        $this->assertTrue($payload['created']);
        $this->assertStringContainsString('/external/invite/', $payload['url']);
        $this->assertStringNotContainsString('renew=1', $payload['url']);

        $this->assertDatabaseHas('users', [
            'email' => 'customer-cabinet@example.test',
            'is_external' => true,
            'contractor_id' => $contractor->id,
            'contractor_contact_id' => $contact->id,
            'external_party' => 'customer',
        ]);

        $reissue = app(ExternalUserProvisionService::class)->provisionInvite(
            $contractor,
            $contact,
            $staff,
        );
        $this->assertFalse($reissue['created']);
        $this->assertStringContainsString('renew=1', $reissue['url']);
    }

    public function test_set_traklo_primary_clears_other_contacts(): void
    {
        if (! Schema::hasColumn('contractor_contacts', 'is_traklo_primary')) {
            $this->markTestSkipped('is_traklo_primary migration is not applied.');
        }

        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Заказ',
        ]);
        $first = ContractorContact::query()->create([
            'contractor_id' => $contractor->id,
            'full_name' => 'A',
            'email' => 'a@test.test',
            'is_traklo_primary' => true,
        ]);
        $second = ContractorContact::query()->create([
            'contractor_id' => $contractor->id,
            'full_name' => 'B',
            'email' => 'b@test.test',
            'is_traklo_primary' => false,
        ]);

        app(ExternalUserProvisionService::class)->setTrakloPrimary($contractor, $second);

        $this->assertFalse($first->fresh()->is_traklo_primary);
        $this->assertTrue($second->fresh()->is_traklo_primary);
    }
}
