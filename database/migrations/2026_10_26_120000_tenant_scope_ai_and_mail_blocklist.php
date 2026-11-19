<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addTenantColumn('ai_interaction_events');
        $this->addTenantColumn('ai_tool_audit_logs');

        if (Schema::hasTable('ai_interaction_events') && Schema::hasColumn('ai_interaction_events', 'tenant_id')) {
            DB::statement('
                UPDATE ai_interaction_events event
                INNER JOIN users u ON u.id = event.user_id
                SET event.tenant_id = u.tenant_id
                WHERE event.tenant_id IS NULL AND u.tenant_id IS NOT NULL
            ');
        }

        if (Schema::hasTable('ai_tool_audit_logs') && Schema::hasColumn('ai_tool_audit_logs', 'tenant_id')) {
            DB::statement('
                UPDATE ai_tool_audit_logs audit
                INNER JOIN users u ON u.id = audit.user_id
                SET audit.tenant_id = u.tenant_id
                WHERE audit.tenant_id IS NULL AND u.tenant_id IS NOT NULL
            ');
        }

        if (Schema::hasTable('mcp_data_links') && Schema::hasColumn('mcp_data_links', 'tenant_id')) {
            Schema::table('mcp_data_links', function (Blueprint $table): void {
                $table->dropUnique('mcp_data_links_pair_unique');
                $table->unique(['tenant_id', 'source_key', 'target_key'], 'mcp_data_links_tenant_pair_unique');
            });
        }

        if (Schema::hasTable('mail_blocked_senders') && Schema::hasColumn('mail_blocked_senders', 'email')) {
            Schema::table('mail_blocked_senders', function (Blueprint $table): void {
                $table->dropUnique(['email']);
            });

            Schema::table('mail_blocked_senders', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'email']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mail_blocked_senders')) {
            Schema::table('mail_blocked_senders', function (Blueprint $table): void {
                $table->dropUnique(['tenant_id', 'email']);
                $table->unique('email');
            });
        }

        if (Schema::hasTable('mcp_data_links')) {
            Schema::table('mcp_data_links', function (Blueprint $table): void {
                $table->dropUnique('mcp_data_links_tenant_pair_unique');
                $table->unique(['source_key', 'target_key'], 'mcp_data_links_pair_unique');
            });
        }

        foreach (['ai_tool_audit_logs', 'ai_interaction_events'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('tenant_id');
            });
        }
    }

    private function addTenantColumn(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'tenant_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
            $blueprint->index('tenant_id');
        });
    }
};
