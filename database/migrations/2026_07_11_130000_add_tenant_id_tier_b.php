<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tier B + remaining operational tables: tenant config, fleet, sales scripts, order graph.
     *
     * @var list<string>
     */
    private array $tables = [
        'cargos',
        'route_points',
        'cargo_leg',
        'cargo_unloading_points',
        'leg_costs',
        'leg_contractor_assignments',
        'financial_terms',
        'contractor_interactions',
        'order_document_edo_acknowledgements',
        'disposition_entries',
        'external_user_invites',
        'order_portal_invites',
        'load_board_posts',
        'load_board_offers',
        'load_board_rate_observations',
        'fleet_trips',
        'fleet_trip_cost_lines',
        'procurement_cases',
        'order_intake_drafts',
        'order_intake_golden_records',
        'order_intake_phrase_learnings',
        'mail_blocked_senders',
        'user_mobile_devices',
        'business_processes',
        'business_process_stages',
        'lead_process_stage_logs',
        'sales_scripts',
        'sales_script_versions',
        'sales_script_nodes',
        'sales_script_transitions',
        'sales_script_play_sessions',
        'sales_script_play_events',
        'sales_script_trainer_messages',
        'sales_script_reaction_classes',
        'sales_script_capture_fields',
        'sales_script_play_session_field_values',
        'sales_script_node_templates',
        'sales_book_articles',
        'sales_book_article_feedback',
        'sales_book_quiz_attempts',
        'fleet_vehicles',
        'fleet_drivers',
        'fleet_vehicle_documents',
        'fleet_driver_documents',
        'kpi_settings',
        'kpi_thresholds',
        'kpi_deduction_rules',
        'order_numbering_rules',
        'proposal_html_templates',
        'proposal_html_template_variables',
        'print_form_basic_terms',
        'mcp_data_links',
        'loading_planner_projects',
        'loading_cargo_groups',
        'loading_cargo_items',
        'transport_templates',
        'management_bank_accounts',
        'management_expense_categories',
        'management_statement_imports',
        'management_statement_lines',
        'management_statement_line_splits',
        'management_payroll_halves',
        'management_payroll_half_users',
        'management_reconcile_rules',
        'budget_scenarios',
        'budget_opex_articles',
        'budget_plan_snapshots',
        'budget_plan_snapshot_lines',
        'budget_sales_targets',
        'company_initiatives',
        'company_initiative_milestones',
        'company_initiative_dependencies',
        'salary_periods',
        'salary_coefficients',
        'salary_accruals',
        'salary_payouts',
        'salary_payout_allocations',
    ];

    public function up(): void
    {
        $demoTenantId = $this->resolveDemoTenantId();

        foreach ($this->tables as $table) {
            $this->addTenantColumn($table);
        }

        $this->backfillFromOrders('cargos', 'order_id');
        $this->backfillFromOrders('financial_terms', 'order_id');
        $this->backfillFromOrders('disposition_entries', 'order_id');
        $this->backfillFromOrders('order_intake_drafts', 'order_id');
        $this->backfillFromOrders('procurement_cases', 'order_id');
        $this->backfillFromOrders('load_board_posts', 'order_id');
        $this->backfillFromOrders('load_board_offers', 'order_id');
        $this->backfillFromOrders('load_board_rate_observations', 'order_id');

        $this->backfillFromOrderLegs('route_points', 'order_leg_id');
        $this->backfillFromOrderLegs('leg_costs', 'order_leg_id');
        $this->backfillFromOrderLegs('leg_contractor_assignments', 'order_leg_id');

        $this->backfillFromCargos('cargo_leg', 'cargo_id');
        $this->backfillFromOrderLegs('cargo_leg', 'order_leg_id');

        if (Schema::hasTable('cargo_unloading_points') && Schema::hasColumn('cargo_unloading_points', 'cargo_id')) {
            DB::statement('
                UPDATE cargo_unloading_points cup
                INNER JOIN cargos c ON c.id = cup.cargo_id
                SET cup.tenant_id = c.tenant_id
                WHERE cup.tenant_id IS NULL AND c.tenant_id IS NOT NULL
            ');
        }

        if (Schema::hasTable('cargo_unloading_points') && Schema::hasColumn('cargo_unloading_points', 'route_point_id')) {
            DB::statement('
                UPDATE cargo_unloading_points cup
                INNER JOIN route_points rp ON rp.id = cup.route_point_id
                SET cup.tenant_id = rp.tenant_id
                WHERE cup.tenant_id IS NULL AND rp.tenant_id IS NOT NULL
            ');
        }

        $this->backfillFromContractors('contractor_interactions', 'contractor_id');
        $this->backfillFromContractors('fleet_vehicles', 'owner_contractor_id');

        if (Schema::hasTable('order_document_edo_acknowledgements') && Schema::hasColumn('order_document_edo_acknowledgements', 'order_document_id')) {
            DB::statement('
                UPDATE order_document_edo_acknowledgements ack
                INNER JOIN order_documents od ON od.id = ack.order_document_id
                SET ack.tenant_id = od.tenant_id
                WHERE ack.tenant_id IS NULL AND od.tenant_id IS NOT NULL
            ');
        }

        $this->backfillFromOrders('fleet_trips', 'order_id');
        $this->backfillFromOrders('order_portal_invites', 'order_id');

        $this->backfillFromContractors('external_user_invites', 'contractor_id');
        $this->backfillFromUsers('external_user_invites', 'created_by');
        $this->backfillFromUsers('order_portal_invites', 'created_by');
        $this->backfillFromUsers('mail_blocked_senders', 'created_by');
        $this->backfillFromUsers('user_mobile_devices', 'user_id');
        $this->backfillFromUsers('loading_planner_projects', 'user_id');
        $this->backfillFromUsers('load_board_posts', 'seller_id');
        $this->backfillFromUsers('order_intake_phrase_learnings', 'user_id');

        if (Schema::hasTable('load_board_posts') && Schema::hasColumn('load_board_posts', 'lead_id')) {
            DB::statement('
                UPDATE load_board_posts child
                INNER JOIN leads l ON l.id = child.lead_id
                SET child.tenant_id = l.tenant_id
                WHERE child.tenant_id IS NULL AND l.tenant_id IS NOT NULL
            ');
        }

        if (Schema::hasTable('load_board_offers') && Schema::hasColumn('load_board_offers', 'load_board_post_id')) {
            DB::statement('
                UPDATE load_board_offers child
                INNER JOIN load_board_posts parent ON parent.id = child.load_board_post_id
                SET child.tenant_id = parent.tenant_id
                WHERE child.tenant_id IS NULL AND parent.tenant_id IS NOT NULL
            ');
        }

        if (Schema::hasTable('load_board_rate_observations') && Schema::hasColumn('load_board_rate_observations', 'load_board_post_id')) {
            DB::statement('
                UPDATE load_board_rate_observations child
                INNER JOIN load_board_posts parent ON parent.id = child.load_board_post_id
                SET child.tenant_id = parent.tenant_id
                WHERE child.tenant_id IS NULL AND parent.tenant_id IS NOT NULL
            ');
        }

        if (Schema::hasTable('procurement_cases')) {
            $this->backfillFromOrders('procurement_cases', 'order_id');
            $this->backfillFromLeads('procurement_cases', 'lead_id');

            if (Schema::hasColumn('procurement_cases', 'load_board_post_id')) {
                DB::statement('
                    UPDATE procurement_cases child
                    INNER JOIN load_board_posts parent ON parent.id = child.load_board_post_id
                    SET child.tenant_id = parent.tenant_id
                    WHERE child.tenant_id IS NULL AND parent.tenant_id IS NOT NULL
                ');
            }
        }

        if (Schema::hasTable('fleet_trip_cost_lines') && Schema::hasColumn('fleet_trip_cost_lines', 'fleet_trip_id')) {
            DB::statement('
                UPDATE fleet_trip_cost_lines line
                INNER JOIN fleet_trips trip ON trip.id = line.fleet_trip_id
                SET line.tenant_id = trip.tenant_id
                WHERE line.tenant_id IS NULL AND trip.tenant_id IS NOT NULL
            ');
        }

        if (Schema::hasTable('business_process_stages') && Schema::hasColumn('business_process_stages', 'business_process_id')) {
            DB::statement('
                UPDATE business_process_stages stage
                INNER JOIN business_processes bp ON bp.id = stage.business_process_id
                SET stage.tenant_id = bp.tenant_id
                WHERE stage.tenant_id IS NULL AND bp.tenant_id IS NOT NULL
            ');
        }

        if (Schema::hasTable('lead_process_stage_logs') && Schema::hasColumn('lead_process_stage_logs', 'lead_id')) {
            $this->backfillFromLeads('lead_process_stage_logs', 'lead_id');
        }

        $this->backfillFromParent('sales_script_versions', 'sales_scripts', 'sales_script_id');
        $this->backfillFromParent('sales_script_nodes', 'sales_script_versions', 'sales_script_version_id');
        $this->backfillFromParent('sales_script_transitions', 'sales_script_versions', 'sales_script_version_id');
        $this->backfillFromParent('sales_script_play_sessions', 'sales_script_versions', 'sales_script_version_id');
        $this->backfillFromParent('sales_script_capture_fields', 'sales_script_versions', 'sales_script_version_id');
        $this->backfillFromParent('sales_script_node_templates', 'sales_script_versions', 'sales_script_version_id');
        $this->backfillFromParent('sales_script_play_events', 'sales_script_play_sessions', 'sales_script_play_session_id');
        $this->backfillFromParent('sales_script_trainer_messages', 'sales_script_play_sessions', 'sales_script_play_session_id');
        $this->backfillFromParent('sales_script_play_session_field_values', 'sales_script_play_sessions', 'sales_script_play_session_id');
        $this->backfillFromParent('sales_book_article_feedback', 'sales_book_articles', 'sales_book_article_id');
        $this->backfillFromParent('sales_book_quiz_attempts', 'sales_book_articles', 'sales_book_article_id');
        $this->backfillFromParent('fleet_vehicle_documents', 'fleet_vehicles', 'fleet_vehicle_id');
        $this->backfillFromParent('fleet_driver_documents', 'fleet_drivers', 'fleet_driver_id');
        $this->backfillFromParent('proposal_html_template_variables', 'proposal_html_templates', 'proposal_html_template_id');
        $this->backfillFromParent('loading_cargo_groups', 'loading_planner_projects', 'loading_planner_project_id');
        $this->backfillFromParent('loading_cargo_items', 'loading_cargo_groups', 'loading_cargo_group_id');
        $this->backfillFromParent('budget_plan_snapshot_lines', 'budget_plan_snapshots', 'budget_plan_snapshot_id');
        $this->backfillFromParent('company_initiative_milestones', 'company_initiatives', 'company_initiative_id');
        $this->backfillFromParent('company_initiative_dependencies', 'company_initiatives', 'company_initiative_id');
        $this->backfillFromParent('management_statement_lines', 'management_statement_imports', 'management_statement_import_id');
        $this->backfillFromParent('management_statement_line_splits', 'management_statement_lines', 'management_statement_line_id');
        $this->backfillFromParent('management_payroll_half_users', 'management_payroll_halves', 'management_payroll_half_id');
        $this->backfillFromParent('salary_accruals', 'salary_periods', 'salary_period_id');
        $this->backfillFromParent('salary_payouts', 'salary_periods', 'salary_period_id');
        $this->backfillFromParent('salary_payout_allocations', 'salary_payouts', 'salary_payout_id');

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

    private function backfillFromOrderLegs(string $table, string $foreignKey): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $foreignKey)) {
            return;
        }

        DB::statement("
            UPDATE {$table} child
            INNER JOIN order_legs ol ON ol.id = child.{$foreignKey}
            INNER JOIN orders o ON o.id = ol.order_id
            SET child.tenant_id = o.tenant_id
            WHERE child.tenant_id IS NULL AND o.tenant_id IS NOT NULL
        ");
    }

    private function backfillFromCargos(string $table, string $foreignKey): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $foreignKey)) {
            return;
        }

        DB::statement("
            UPDATE {$table} child
            INNER JOIN cargos c ON c.id = child.{$foreignKey}
            SET child.tenant_id = c.tenant_id
            WHERE child.tenant_id IS NULL AND c.tenant_id IS NOT NULL
        ");
    }

    private function backfillFromParent(string $childTable, string $parentTable, string $foreignKey): void
    {
        if (! Schema::hasTable($childTable) || ! Schema::hasTable($parentTable)) {
            return;
        }

        if (! Schema::hasColumn($childTable, $foreignKey) || ! Schema::hasColumn($parentTable, 'tenant_id')) {
            return;
        }

        DB::statement("
            UPDATE {$childTable} child
            INNER JOIN {$parentTable} parent ON parent.id = child.{$foreignKey}
            SET child.tenant_id = parent.tenant_id
            WHERE child.tenant_id IS NULL AND parent.tenant_id IS NOT NULL
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
