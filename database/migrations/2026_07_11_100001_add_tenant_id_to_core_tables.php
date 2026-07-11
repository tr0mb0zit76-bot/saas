<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['users', 'contractors', 'leads', 'orders'];

    public function up(): void
    {
        $tenantId = DB::table('tenants')->insertGetId([
            'slug' => 'demo',
            'name' => 'Demo Logistics',
            'status' => 'active',
            'plan' => 'start',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            });

            DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};
