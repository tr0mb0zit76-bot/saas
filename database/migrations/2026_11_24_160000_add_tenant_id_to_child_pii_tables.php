<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addTenantId('commercial_ai_suggestion_logs', function (): void {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('UPDATE commercial_ai_suggestion_logs SET tenant_id = (SELECT tenant_id FROM users WHERE users.id = commercial_ai_suggestion_logs.user_id) WHERE tenant_id IS NULL');

                return;
            }

            DB::table('commercial_ai_suggestion_logs as l')
                ->join('users as u', 'u.id', '=', 'l.user_id')
                ->whereNull('l.tenant_id')
                ->update(['l.tenant_id' => DB::raw('u.tenant_id')]);
        });

        $this->addTenantId('contractor_portraits', function (): void {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('UPDATE contractor_portraits SET tenant_id = (SELECT tenant_id FROM contractors WHERE contractors.id = contractor_portraits.contractor_id) WHERE tenant_id IS NULL');

                return;
            }

            DB::table('contractor_portraits as p')
                ->join('contractors as c', 'c.id', '=', 'p.contractor_id')
                ->whereNull('p.tenant_id')
                ->update(['p.tenant_id' => DB::raw('c.tenant_id')]);
        });

        $this->addTenantId('contractor_risk_assessments', function (): void {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('UPDATE contractor_risk_assessments SET tenant_id = (SELECT tenant_id FROM contractors WHERE contractors.id = contractor_risk_assessments.contractor_id) WHERE tenant_id IS NULL');

                return;
            }

            DB::table('contractor_risk_assessments as r')
                ->join('contractors as c', 'c.id', '=', 'r.contractor_id')
                ->whereNull('r.tenant_id')
                ->update(['r.tenant_id' => DB::raw('c.tenant_id')]);
        });
    }

    public function down(): void
    {
        foreach (['commercial_ai_suggestion_logs', 'contractor_portraits', 'contractor_risk_assessments'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('tenant_id');
            });
        }
    }

    private function addTenantId(string $table, callable $backfill): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'tenant_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->unsignedBigInteger('tenant_id')->nullable()->index();
        });

        $backfill();
    }
};
