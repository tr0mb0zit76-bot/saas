<template>
    <section class="shrink-0 space-y-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 space-y-1">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Контроль плана оплат</h2>
                <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                    План и факт по графику заказов
                    <span v-if="dashboard.mode === 'schedules_only'">(Pro: без банковской выписки).</span>
                    <span v-else>(Enterprise: «Сейчас» из тонкой кассы; join выписки в gauges — позже).</span>
                    Не путать с упр. ОПиУ.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <label class="text-[10px] font-medium uppercase tracking-wide text-zinc-500">Период</label>
                <select
                    class="rounded border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-950"
                    :value="dashboard.period"
                    @change="onFilter('cf_period', $event.target.value)"
                >
                    <option v-for="opt in dashboard.period_options || []" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>

                <label class="text-[10px] font-medium uppercase tracking-wide text-zinc-500">Направление</label>
                <select
                    class="max-w-[12rem] rounded border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-950"
                    :value="dashboard.direction ?? ''"
                    @change="onFilter('cf_direction', $event.target.value || null)"
                >
                    <option
                        v-for="opt in dashboard.direction_options || []"
                        :key="String(opt.value)"
                        :value="opt.value ?? ''"
                    >
                        {{ opt.label }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Three honest lenses -->
        <div class="grid gap-2 md:grid-cols-3">
            <div class="rounded border border-zinc-200 p-3 dark:border-zinc-700">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Сейчас</div>
                <div class="mt-0.5 text-[11px] text-zinc-400">Только касса / банк</div>
                <template v-if="lenses.cash_now?.available && lenses.cash_now?.value">
                    <div class="mt-2 text-lg font-semibold tabular-nums text-zinc-900 dark:text-zinc-50">
                        {{ formatMoney(lenses.cash_now.value.balance_now) }}
                    </div>
                    <p class="mt-1 text-[10px] leading-4 text-zinc-500">{{ lenses.cash_now.value.disclaimer }}</p>
                </template>
                <p v-else class="mt-2 text-xs text-zinc-500">Доступно на Enterprise (тонкая касса УУ).</p>
            </div>

            <div class="rounded border border-emerald-200 bg-emerald-50/40 p-3 dark:border-emerald-900 dark:bg-emerald-950/20">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                    Факт + ожидания (ДЗ/КЗ)
                </div>
                <div class="mt-0.5 text-[11px] text-zinc-500">Оверлей графика, не начисление</div>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded px-2 py-1 text-[11px] font-medium"
                        :class="dashboard.overlay === 'fact_only' ? activeChip : idleChip"
                        @click="onFilter('cf_overlay', 'fact_only')"
                    >
                        Только факт
                    </button>
                    <button
                        type="button"
                        class="rounded px-2 py-1 text-[11px] font-medium"
                        :class="dashboard.overlay === 'fact_plus_expectations' ? activeChip : idleChip"
                        @click="onFilter('cf_overlay', 'fact_plus_expectations')"
                    >
                        Факт + ожидания
                    </button>
                </div>
                <div v-if="expectations" class="mt-2 grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <div class="text-[10px] text-zinc-500">Ожид. поступления</div>
                        <div class="font-semibold tabular-nums text-sky-700 dark:text-sky-300">
                            {{ formatMoney(expectations.expected_in) }}
                        </div>
                    </div>
                    <div>
                        <div class="text-[10px] text-zinc-500">Ожид. оплаты</div>
                        <div class="font-semibold tabular-nums text-amber-700 dark:text-amber-300">
                            {{ formatMoney(expectations.expected_out) }}
                        </div>
                    </div>
                </div>
                <p v-if="dashboard.projection?.note" class="mt-2 text-[10px] leading-4 text-zinc-500">
                    {{ dashboard.projection.note }}
                    <span v-if="dashboard.projection.projected !== null" class="font-semibold tabular-nums text-zinc-800 dark:text-zinc-100">
                        → {{ formatMoney(dashboard.projection.projected) }}
                    </span>
                </p>
            </div>

            <div class="rounded border border-zinc-200 p-3 dark:border-zinc-700">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Упр. ОПиУ</div>
                <div class="mt-0.5 text-[11px] text-zinc-400">Начисление — отдельно</div>
                <p class="mt-2 text-xs leading-5 text-zinc-500">
                    {{ lenses.management_pnl?.message || 'Позже (P2). Не подменять кассой.' }}
                </p>
            </div>
        </div>

        <!-- Flow toggle + gauges -->
        <div class="flex flex-wrap gap-2">
            <button
                v-for="flow in flowOptions"
                :key="flow.value"
                type="button"
                class="rounded px-2.5 py-1 text-xs font-medium"
                :class="dashboard.flow === flow.value ? activeChip : idleChip"
                @click="onFilter('cf_flow', flow.value)"
            >
                {{ flow.label }}
            </button>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <div
                v-for="key in ['incomes', 'expenses', 'total']"
                :key="key"
                class="rounded border border-zinc-200 p-3 dark:border-zinc-700"
                :class="dashboard.flow === key ? 'ring-1 ring-zinc-400 dark:ring-zinc-500' : ''"
            >
                <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">
                    {{ gauges[key]?.label || key }}
                </div>
                <div class="mt-2 grid grid-cols-3 gap-1 text-center">
                    <div>
                        <div class="text-[10px] text-zinc-500">План</div>
                        <div class="text-xs font-semibold tabular-nums">{{ formatMoney(gauges[key]?.plan) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-zinc-500">Факт</div>
                        <div class="text-xs font-semibold tabular-nums">{{ formatMoney(gauges[key]?.fact) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-zinc-500">%</div>
                        <div class="text-sm font-bold tabular-nums" :class="pctClass(gauges[key]?.pct)">
                            {{ formatPct(gauges[key]?.pct) }}
                        </div>
                    </div>
                </div>
                <div class="mt-2 h-1.5 overflow-hidden rounded bg-zinc-100 dark:bg-zinc-800">
                    <div
                        class="h-full rounded bg-sky-500 transition-all"
                        :style="{ width: `${Math.min(100, gauges[key]?.pct ?? 0)}%` }"
                    />
                </div>
            </div>
        </div>

        <!-- Accuracy chart (simple bars) -->
        <div v-if="(dashboard.accuracy || []).length" class="space-y-2">
            <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">
                Точность планирования по неделям
            </div>
            <div class="flex items-end gap-1 overflow-x-auto pb-1" style="min-height: 4.5rem">
                <div
                    v-for="bucket in dashboard.accuracy"
                    :key="bucket.bucket"
                    class="flex w-10 shrink-0 flex-col items-center gap-1"
                    :title="`${bucket.label}: план ${formatMoney(bucket.plan)}, факт ${formatMoney(bucket.fact)}`"
                >
                    <div class="text-[9px] tabular-nums text-zinc-500">{{ formatPct(bucket.pct) }}</div>
                    <div class="flex h-14 w-full items-end justify-center gap-0.5">
                        <div
                            class="w-2 rounded-t bg-zinc-300 dark:bg-zinc-600"
                            :style="{ height: `${barHeight(bucket.plan)}%` }"
                        />
                        <div
                            class="w-2 rounded-t bg-sky-500"
                            :style="{ height: `${barHeight(bucket.fact)}%` }"
                        />
                    </div>
                    <div class="text-[8px] leading-none text-zinc-400">{{ bucket.label.split('–')[0] }}</div>
                </div>
            </div>
            <div class="flex gap-3 text-[10px] text-zinc-500">
                <span><span class="inline-block h-2 w-2 rounded-sm bg-zinc-300 dark:bg-zinc-600" /> план</span>
                <span><span class="inline-block h-2 w-2 rounded-sm bg-sky-500" /> факт</span>
            </div>
        </div>

        <!-- Directions breakdown -->
        <div v-if="(dashboard.by_direction || []).length" class="overflow-x-auto">
            <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-zinc-500">По направлениям</div>
            <table class="min-w-full text-left text-xs">
                <thead class="text-[10px] uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="py-1 pr-3 font-medium">Направление</th>
                        <th class="py-1 pr-3 font-medium">План</th>
                        <th class="py-1 pr-3 font-medium">Факт</th>
                        <th class="py-1 font-medium">%</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in dashboard.by_direction"
                        :key="row.key"
                        class="border-t border-zinc-100 dark:border-zinc-800"
                    >
                        <td class="py-1.5 pr-3">
                            <button
                                type="button"
                                class="text-left font-medium text-zinc-800 hover:underline dark:text-zinc-100"
                                @click="onFilter('cf_direction', row.key)"
                            >
                                {{ row.label }}
                            </button>
                        </td>
                        <td class="py-1.5 pr-3 tabular-nums">{{ formatMoney(row.plan) }}</td>
                        <td class="py-1.5 pr-3 tabular-nums">{{ formatMoney(row.fact) }}</td>
                        <td class="py-1.5 tabular-nums" :class="pctClass(row.pct)">{{ formatPct(row.pct) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    dashboard: {
        type: Object,
        default: () => ({}),
    },
});

const activeChip = 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900';
const idleChip = 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700';

const flowOptions = [
    { value: 'incomes', label: 'Поступления' },
    { value: 'expenses', label: 'Списания' },
    { value: 'total', label: 'Итого' },
];

const lenses = computed(() => props.dashboard?.lenses ?? {});
const gauges = computed(() => props.dashboard?.gauges ?? {});
const expectations = computed(() => lenses.value?.expectations?.value ?? null);

const maxBar = computed(() => {
    const rows = props.dashboard?.accuracy ?? [];
    const max = Math.max(0, ...rows.flatMap((r) => [Number(r.plan) || 0, Number(r.fact) || 0]));

    return max > 0 ? max : 1;
});

function onFilter(key, value) {
    const params = new URLSearchParams(window.location.search);
    params.set('section', 'cashflow');
    params.set('cashflow_tab', 'schedule');

    if (value === null || value === '' || value === undefined) {
        params.delete(key);
    } else {
        params.set(key, String(value));
    }

    router.get(`/finance?${params.toString()}`, {}, { preserveScroll: true, preserveState: true });
}

function formatMoney(value) {
    const amount = Number(value) || 0;

    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatPct(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    return `${Number(value).toFixed(0)}%`;
}

function pctClass(value) {
    if (value === null || value === undefined) {
        return 'text-zinc-400';
    }
    if (value >= 90) {
        return 'text-emerald-600 dark:text-emerald-400';
    }
    if (value >= 60) {
        return 'text-amber-600 dark:text-amber-400';
    }

    return 'text-rose-600 dark:text-rose-400';
}

function barHeight(value) {
    return Math.max(4, Math.round(((Number(value) || 0) / maxBar.value) * 100));
}
</script>
