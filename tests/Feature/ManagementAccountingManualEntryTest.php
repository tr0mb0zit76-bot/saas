<?php

namespace Tests\Feature;

use App\Models\ManagementBankAccount;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ManagementAccounting\ManagementExpenseCategorySyncService;
use App\Support\TenantContext;
use Tests\TestCase;

class ManagementAccountingManualEntryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $tenant = TenantContext::get() ?? Tenant::query()->where('slug', 'demo')->first();
        if ($tenant === null) {
            return;
        }

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings['onboarding'] = ['completed_at' => now()->toIso8601String()];
        $tenant->forceFill([
            'plan' => 'enterprise',
            'settings' => $settings,
        ])->save();

        TenantContext::set($tenant->fresh());
    }

    public function test_manual_cash_expense_creates_allocated_line(): void
    {
        app(ManagementExpenseCategorySyncService::class)->syncAll();
        ManagementBankAccount::ensureManualInstruments();

        $role = Role::query()->create([
            'name' => 'accountant',
            'display_name' => 'Accountant',
            'visibility_areas' => ['finance_payment_reconcile'],
        ]);

        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Cash Clerk',
            'email' => 'cash-clerk@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'can_management_accounting' => true,
        ]);

        $cash = ManagementBankAccount::cash();
        $category = ManagementExpenseCategory::query()
            ->where('code', 'cash_other_out')
            ->firstOrFail();

        $this->actingAs($user)
            ->post(route('finance.management-accounting.manual-entries.store'), [
                'bank_account_id' => $cash->id,
                'operation_date' => '2026-11-20',
                'direction' => 'out',
                'amount' => 1500.50,
                'currency' => 'RUB',
                'description' => 'Такси наличными',
                'allocation_type' => 'category',
                'category_id' => $category->id,
            ])
            ->assertRedirect(route('finance.management-accounting.index'));

        $line = ManagementStatementLine::query()
            ->where('source', 'manual')
            ->where('description', 'Такси наличными')
            ->first();

        $this->assertNotNull($line);
        $this->assertSame('allocated', $line->status);
        $this->assertSame('manual', $line->source);
        $this->assertSame('out', $line->direction);
        $this->assertSame((int) $cash->id, (int) $line->bank_account_id);
        $this->assertSame((int) $category->id, (int) $line->allocation_category_id);
        $this->assertSame(1500.5, (float) $line->amount);
    }

    public function test_manual_entry_rejects_category_with_mismatched_flow(): void
    {
        app(ManagementExpenseCategorySyncService::class)->syncAll();
        ManagementBankAccount::ensureManualInstruments();

        $role = Role::query()->create([
            'name' => 'accountant',
            'display_name' => 'Accountant',
            'visibility_areas' => ['finance_payment_reconcile'],
        ]);

        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Cash Clerk 2',
            'email' => 'cash-clerk-2@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'can_management_accounting' => true,
        ]);

        $cash = ManagementBankAccount::cash();
        $incomeCategory = ManagementExpenseCategory::query()
            ->where('code', 'cash_other_in')
            ->firstOrFail();

        $this->actingAs($user)
            ->from(route('finance.management-accounting.index'))
            ->post(route('finance.management-accounting.manual-entries.store'), [
                'bank_account_id' => $cash->id,
                'operation_date' => '2026-11-20',
                'direction' => 'out',
                'amount' => 100,
                'description' => 'Неверная статья',
                'allocation_type' => 'category',
                'category_id' => $incomeCategory->id,
            ])
            ->assertRedirect(route('finance.management-accounting.index'))
            ->assertSessionHasErrors('category_id');

        $this->assertSame(0, ManagementStatementLine::query()->where('description', 'Неверная статья')->count());
    }

    public function test_index_exposes_cash_and_card_instruments(): void
    {
        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'visibility_areas' => ['documents'],
        ]);

        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Accountant',
            'email' => 'ma-instruments@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'can_management_accounting' => true,
        ]);

        $this->actingAs($user)
            ->get('/finance/management-accounting')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/ManagementAccounting/Index')
                ->has('bank_accounts')
                ->where('bank_accounts', function ($accounts): bool {
                    $instruments = collect($accounts)->pluck('instrument')->all();

                    return in_array('cash', $instruments, true)
                        && in_array('corp_card', $instruments, true);
                }));
    }
}
