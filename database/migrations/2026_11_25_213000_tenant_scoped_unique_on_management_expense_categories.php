<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * System category codes (group_expense, bank_fees, …) must exist per tenant.
 * Global UNIQUE(code) made sync fail for every tenant after the first → empty
 * «Отчёт по статьям» (only Валовая маржа / Прибыль).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('management_expense_categories')) {
            return;
        }

        if (Schema::hasColumn('management_expense_categories', 'tenant_id')) {
            $fallbackTenantId = DB::table('tenants')->orderBy('id')->value('id');

            if ($fallbackTenantId !== null) {
                DB::table('management_expense_categories')
                    ->whereNull('tenant_id')
                    ->update(['tenant_id' => $fallbackTenantId]);
            }
        }

        Schema::table('management_expense_categories', function (Blueprint $table): void {
            if ($this->hasIndex('management_expense_categories_code_unique')) {
                $table->dropUnique('management_expense_categories_code_unique');
            }

            if (! $this->hasIndex('mgmt_exp_cat_tenant_code_unique')
                && Schema::hasColumn('management_expense_categories', 'tenant_id')) {
                $table->unique(['tenant_id', 'code'], 'mgmt_exp_cat_tenant_code_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('management_expense_categories')) {
            return;
        }

        Schema::table('management_expense_categories', function (Blueprint $table): void {
            if ($this->hasIndex('mgmt_exp_cat_tenant_code_unique')) {
                $table->dropUnique('mgmt_exp_cat_tenant_code_unique');
            }

            if (! $this->hasIndex('management_expense_categories_code_unique')) {
                $table->unique('code', 'management_expense_categories_code_unique');
            }
        });
    }

    private function hasIndex(string $name): bool
    {
        $indexes = Schema::getIndexes('management_expense_categories');

        return in_array($name, array_column($indexes, 'name'), true);
    }
};
