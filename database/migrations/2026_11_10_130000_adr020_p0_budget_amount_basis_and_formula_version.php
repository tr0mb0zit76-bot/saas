<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-020 P0: amount_basis (total|per_head) + snapshot formula_version.
 *
 * Orientum / legacy «Оклады менеджеров» were lump totals (e.g. 450_000 for N=3).
 * Convert once: amount_basis=per_head, rate = round(total / manager_count).
 * Other fixed articles stay amount_basis=total so numbers stay backward-compatible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('budget_opex_articles')
            && ! Schema::hasColumn('budget_opex_articles', 'amount_basis')) {
            Schema::table('budget_opex_articles', function (Blueprint $table): void {
                $table->string('amount_basis', 16)
                    ->default('total')
                    ->after('cost_type')
                    ->comment('total = на всех; per_head = ставка × manager_count');
            });
        }

        if (Schema::hasTable('budget_plan_snapshots')
            && ! Schema::hasColumn('budget_plan_snapshots', 'formula_version')) {
            Schema::table('budget_plan_snapshots', function (Blueprint $table): void {
                $table->string('formula_version', 32)
                    ->nullable()
                    ->after('notes');
            });
        }

        $this->convertLegacyManagerPayrollLumps();
    }

    public function down(): void
    {
        if (Schema::hasTable('budget_opex_articles')
            && Schema::hasColumn('budget_opex_articles', 'amount_basis')) {
            Schema::table('budget_opex_articles', function (Blueprint $table): void {
                $table->dropColumn('amount_basis');
            });
        }

        if (Schema::hasTable('budget_plan_snapshots')
            && Schema::hasColumn('budget_plan_snapshots', 'formula_version')) {
            Schema::table('budget_plan_snapshots', function (Blueprint $table): void {
                $table->dropColumn('formula_version');
            });
        }
    }

    private function convertLegacyManagerPayrollLumps(): void
    {
        if (! Schema::hasTable('budget_opex_articles')
            || ! Schema::hasColumn('budget_opex_articles', 'amount_basis')) {
            return;
        }

        $articles = DB::table('budget_opex_articles')
            ->where('name', 'Оклады менеджеров')
            ->where(function ($query): void {
                $query->whereNull('amount_basis')->orWhere('amount_basis', 'total');
            })
            ->get(['id', 'tenant_id', 'amount_monthly', 'ramp_months']);

        foreach ($articles as $article) {
            $managerCount = $this->managerCountForTenant($article->tenant_id ?? null);
            $total = (float) $article->amount_monthly;
            $rate = $managerCount > 0 ? round($total / $managerCount, 2) : $total;

            DB::table('budget_opex_articles')
                ->where('id', $article->id)
                ->update([
                    'amount_basis' => 'per_head',
                    'amount_monthly' => $rate,
                    // ADR-020: salary default = весь горизонт (null), not «первые N мес.»
                    'ramp_months' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    private function managerCountForTenant(mixed $tenantId): int
    {
        if ($tenantId === null || ! Schema::hasTable('budget_scenarios')) {
            return 3;
        }

        $query = DB::table('budget_scenarios')->orderBy('id');

        if (Schema::hasColumn('budget_scenarios', 'tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        $inputs = $query->value('inputs');

        if (is_string($inputs)) {
            $decoded = json_decode($inputs, true);
            $inputs = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($inputs)) {
            return 3;
        }

        $count = (int) ($inputs['manager_count'] ?? 3);

        return max(1, min(100, $count));
    }
};
