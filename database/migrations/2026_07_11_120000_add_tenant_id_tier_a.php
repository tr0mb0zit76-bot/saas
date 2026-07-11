<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tier A: tenant_id on operational tables + AI audit tables.
     *
     * Backfill order: parent FK joins, then demo tenant fallback.
     *
     * @var list<string>
     */
    private array $tables = [
        'tasks',
        'task_attachments',
        'task_comments',
        'task_events',
        'task_checklist_items',
        'order_documents',
        'order_legs',
        'order_status_logs',
        'payment_schedules',
        'payment_schedule_payment_events',
        'lead_attachments',
        'lead_offers',
        'lead_activities',
        'lead_cargo_items',
        'lead_route_points',
        'contractor_documents',
        'contractor_contacts',
        'finance_documents',
        'mail_threads',
        'mail_messages',
        'conversations',
        'conversation_participants',
        'chat_messages',
        'activity_events',
        'grid_views',
        'print_form_templates',
        'departments',
        'roles',
        'agent_conversations',
        'agent_conversation_messages',
        'ai_conversations',
        'ai_messages',
        'ai_attachments',
        'ai_feedback_log',
        'ai_knowledge_index',
        'ai_order_drafts',
        'ai_parser_logs',
    ];

    public function up(): void
    {
        $demoTenantId = $this->resolveDemoTenantId();

        foreach ($this->tables as $table) {
            $this->addTenantColumn($table);
        }

        $this->backfillFromOrders('order_documents', 'order_id');
        $this->backfillFromOrders('order_legs', 'order_id');
        $this->backfillFromOrders('order_status_logs', 'order_id');
        $this->backfillFromOrders('payment_schedules', 'order_id');

        if (Schema::hasTable('payment_schedule_payment_events') && Schema::hasColumn('payment_schedule_payment_events', 'payment_schedule_id')) {
            DB::statement('
                UPDATE payment_schedule_payment_events e
                INNER JOIN payment_schedules ps ON ps.id = e.payment_schedule_id
                SET e.tenant_id = ps.tenant_id
                WHERE e.tenant_id IS NULL AND ps.tenant_id IS NOT NULL
            ');
        }

        $this->backfillFromLeads('lead_attachments', 'lead_id');
        $this->backfillFromLeads('lead_offers', 'lead_id');
        $this->backfillFromLeads('lead_activities', 'lead_id');
        $this->backfillFromLeads('lead_cargo_items', 'lead_id');
        $this->backfillFromLeads('lead_route_points', 'lead_id');

        $this->backfillFromContractors('contractor_documents', 'contractor_id');
        $this->backfillFromContractors('contractor_contacts', 'contractor_id');

        $this->backfillFromUsers('mail_threads', 'mailbox_user_id');
        $this->backfillFromUsers('grid_views', 'user_id');
        $this->backfillFromUsers('activity_events', 'user_id');
        $this->backfillFromUsers('agent_conversations', 'user_id');
        $this->backfillFromUsers('ai_conversations', 'user_id');
        $this->backfillFromUsers('ai_order_drafts', 'user_id');
        $this->backfillFromUsers('ai_parser_logs', 'user_id');

        if (Schema::hasTable('agent_conversation_messages') && Schema::hasColumn('agent_conversation_messages', 'conversation_id')) {
            DB::statement('
                UPDATE agent_conversation_messages m
                INNER JOIN agent_conversations c ON c.id = m.conversation_id
                SET m.tenant_id = c.tenant_id
                WHERE m.tenant_id IS NULL AND c.tenant_id IS NOT NULL
            ');
        }

        if (Schema::hasTable('ai_messages') && Schema::hasColumn('ai_messages', 'conversation_id')) {
            DB::statement('
                UPDATE ai_messages m
                INNER JOIN ai_conversations c ON c.id = m.conversation_id
                SET m.tenant_id = c.tenant_id
                WHERE m.tenant_id IS NULL AND c.tenant_id IS NOT NULL
            ');
        }

        if (Schema::hasTable('ai_attachments') && Schema::hasColumn('ai_attachments', 'message_id')) {
            DB::statement('
                UPDATE ai_attachments a
                INNER JOIN ai_messages m ON m.id = a.message_id
                SET a.tenant_id = m.tenant_id
                WHERE a.tenant_id IS NULL AND m.tenant_id IS NOT NULL
            ');
        }

        if (Schema::hasTable('ai_feedback_log') && Schema::hasColumn('ai_feedback_log', 'user_id')) {
            $this->backfillFromUsers('ai_feedback_log', 'user_id');
        }

        if (Schema::hasTable('mail_messages') && Schema::hasColumn('mail_messages', 'mail_thread_id')) {
            DB::statement('
                UPDATE mail_messages mm
                INNER JOIN mail_threads mt ON mt.id = mm.mail_thread_id
                SET mm.tenant_id = mt.tenant_id
                WHERE mm.tenant_id IS NULL AND mt.tenant_id IS NOT NULL
            ');
        }

        if (Schema::hasTable('chat_messages') && Schema::hasColumn('chat_messages', 'conversation_id')) {
            DB::statement('
                UPDATE chat_messages cm
                INNER JOIN conversations c ON c.id = cm.conversation_id
                SET cm.tenant_id = c.tenant_id
                WHERE cm.tenant_id IS NULL AND c.tenant_id IS NOT NULL
            ');
        }

        if (Schema::hasTable('conversation_participants') && Schema::hasColumn('conversation_participants', 'conversation_id')) {
            DB::statement('
                UPDATE conversation_participants cp
                INNER JOIN conversations c ON c.id = cp.conversation_id
                SET cp.tenant_id = c.tenant_id
                WHERE cp.tenant_id IS NULL AND c.tenant_id IS NOT NULL
            ');
        }

        // tasks: order first, then lead, then responsible user
        if (Schema::hasTable('tasks')) {
            DB::statement('
                UPDATE tasks t
                INNER JOIN orders o ON o.id = t.order_id
                SET t.tenant_id = o.tenant_id
                WHERE t.tenant_id IS NULL AND t.order_id IS NOT NULL AND o.tenant_id IS NOT NULL
            ');
            DB::statement('
                UPDATE tasks t
                INNER JOIN leads l ON l.id = t.lead_id
                SET t.tenant_id = l.tenant_id
                WHERE t.tenant_id IS NULL AND t.lead_id IS NOT NULL AND l.tenant_id IS NOT NULL
            ');
            DB::statement('
                UPDATE tasks t
                INNER JOIN users u ON u.id = t.responsible_id
                SET t.tenant_id = u.tenant_id
                WHERE t.tenant_id IS NULL AND t.responsible_id IS NOT NULL AND u.tenant_id IS NOT NULL
            ');
        }

        foreach (['task_attachments', 'task_comments', 'task_events', 'task_checklist_items'] as $taskChild) {
            if (Schema::hasTable($taskChild) && Schema::hasColumn($taskChild, 'task_id')) {
                DB::statement("
                    UPDATE {$taskChild} tc
                    INNER JOIN tasks t ON t.id = tc.task_id
                    SET tc.tenant_id = t.tenant_id
                    WHERE tc.tenant_id IS NULL AND t.tenant_id IS NOT NULL
                ");
            }
        }

        foreach ($this->tables as $table) {
            $this->fallbackDemoTenant($table, $demoTenantId);
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

    private function resolveDemoTenantId(): int
    {
        $existing = DB::table('tenants')->where('slug', 'demo')->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('tenants')->insertGetId([
            'slug' => 'demo',
            'name' => 'Demo Logistics',
            'status' => 'active',
            'plan' => 'start',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addTenantColumn(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'tenant_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            $blueprint->index('tenant_id');
        });
    }

    private function backfillFromOrders(string $table, string $foreignKey): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $foreignKey)) {
            return;
        }

        DB::statement("
            UPDATE {$table} child
            INNER JOIN orders o ON o.id = child.{$foreignKey}
            SET child.tenant_id = o.tenant_id
            WHERE child.tenant_id IS NULL AND o.tenant_id IS NOT NULL
        ");
    }

    private function backfillFromLeads(string $table, string $foreignKey): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $foreignKey)) {
            return;
        }

        DB::statement("
            UPDATE {$table} child
            INNER JOIN leads l ON l.id = child.{$foreignKey}
            SET child.tenant_id = l.tenant_id
            WHERE child.tenant_id IS NULL AND l.tenant_id IS NOT NULL
        ");
    }

    private function backfillFromContractors(string $table, string $foreignKey): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $foreignKey)) {
            return;
        }

        DB::statement("
            UPDATE {$table} child
            INNER JOIN contractors c ON c.id = child.{$foreignKey}
            SET child.tenant_id = c.tenant_id
            WHERE child.tenant_id IS NULL AND c.tenant_id IS NOT NULL
        ");
    }

    private function backfillFromUsers(string $table, string $foreignKey): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $foreignKey)) {
            return;
        }

        DB::statement("
            UPDATE {$table} child
            INNER JOIN users u ON u.id = child.{$foreignKey}
            SET child.tenant_id = u.tenant_id
            WHERE child.tenant_id IS NULL AND u.tenant_id IS NOT NULL
        ");
    }

    private function fallbackDemoTenant(string $table, int $demoTenantId): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
            return;
        }

        DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $demoTenantId]);
    }
};
