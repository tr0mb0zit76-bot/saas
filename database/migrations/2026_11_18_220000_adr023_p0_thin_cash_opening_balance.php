<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('management_bank_accounts')) {
            return;
        }

        Schema::table('management_bank_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('management_bank_accounts', 'opening_balance')) {
                $table->decimal('opening_balance', 14, 2)->nullable()->after('currency');
            }
            if (! Schema::hasColumn('management_bank_accounts', 'opening_balance_as_of')) {
                $table->date('opening_balance_as_of')->nullable()->after('opening_balance');
            }
            if (! Schema::hasColumn('management_bank_accounts', 'opening_balance_source')) {
                $table->string('opening_balance_source', 16)->nullable()->after('opening_balance_as_of');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('management_bank_accounts')) {
            return;
        }

        Schema::table('management_bank_accounts', function (Blueprint $table): void {
            foreach (['opening_balance_source', 'opening_balance_as_of', 'opening_balance'] as $column) {
                if (Schema::hasColumn('management_bank_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
