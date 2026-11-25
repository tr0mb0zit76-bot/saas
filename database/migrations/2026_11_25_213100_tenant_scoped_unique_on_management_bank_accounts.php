<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CASH / CORP_CARD / CONSOLIDATED must exist per tenant.
 * Global UNIQUE(account_number) breaks ensureManualInstruments for tenants after the first.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('management_bank_accounts')) {
            return;
        }

        if (Schema::hasColumn('management_bank_accounts', 'tenant_id')) {
            $fallbackTenantId = DB::table('tenants')->orderBy('id')->value('id');

            if ($fallbackTenantId !== null) {
                DB::table('management_bank_accounts')
                    ->whereNull('tenant_id')
                    ->update(['tenant_id' => $fallbackTenantId]);
            }
        }

        Schema::table('management_bank_accounts', function (Blueprint $table): void {
            if ($this->hasIndex('management_bank_accounts_account_number_unique')) {
                $table->dropUnique('management_bank_accounts_account_number_unique');
            }

            if (! $this->hasIndex('mgmt_bank_acct_tenant_number_unique')
                && Schema::hasColumn('management_bank_accounts', 'tenant_id')) {
                $table->unique(['tenant_id', 'account_number'], 'mgmt_bank_acct_tenant_number_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('management_bank_accounts')) {
            return;
        }

        Schema::table('management_bank_accounts', function (Blueprint $table): void {
            if ($this->hasIndex('mgmt_bank_acct_tenant_number_unique')) {
                $table->dropUnique('mgmt_bank_acct_tenant_number_unique');
            }

            if (! $this->hasIndex('management_bank_accounts_account_number_unique')) {
                $table->unique('account_number', 'management_bank_accounts_account_number_unique');
            }
        });
    }

    private function hasIndex(string $name): bool
    {
        $indexes = Schema::getIndexes('management_bank_accounts');

        return in_array($name, array_column($indexes, 'name'), true);
    }
};
