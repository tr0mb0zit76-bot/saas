<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, h } from 'vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmPageLead,
    crmPageTitle,
    crmPanel,
    crmSectionTitle,
    crmStatCard,
} from '@/support/crmUi';

defineOptions({
    layout: (page) =>
        h(CrmLayout, { activeKey: 'finance', activeSubKey: 'finance-balance' }, () => page),
});

const props = defineProps({
    balance: {
        type: Object,
        required: true,
    },
});

const snap = computed(() => props.balance?.snapshot ?? {});
const labels = computed(() => props.balance?.labels ?? {});
const liquidity = computed(() => props.balance?.liquidity ?? {});

function formatMoney(value) {
    if (value === null || value === undefined) {
        return '—';
    }
    const n = typeof value === 'number' ? value : Number(value) || 0;
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(n);
}

function formatRatio(value) {
    if (value === null || value === undefined) {
        return '—';
    }
    return Number(value).toFixed(2);
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <Link
                    href="/finance"
                    class="inline-flex items-center gap-2 text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                >
                    ← К обзору финансов
                </Link>
                <h1 :class="crmPageTitle">{{ balance.title }}</h1>
                <p :class="crmPageLead">{{ balance.subtitle }}</p>
                <p class="max-w-3xl text-xs leading-5 text-amber-700 dark:text-amber-300">
                    {{ balance.disclaimer }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <Link
                    :href="balance.working_capital_url"
                    class="font-medium text-sky-700 hover:underline dark:text-sky-300"
                >
                    Оборотный капитал →
                </Link>
                <Link
                    :href="balance.cashflow_url"
                    class="font-medium text-sky-700 hover:underline dark:text-sky-300"
                >
                    График оплат →
                </Link>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div :class="crmStatCard">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">
                    {{ labels.cash }}
                </div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-50">
                    <template v-if="snap.cash_available">{{ formatMoney(snap.cash) }}</template>
                    <template v-else>—</template>
                </div>
                <p v-if="!snap.cash_available" class="mt-1 text-[10px] text-zinc-500">Нужен доступ к УУ / тонкой кассе.</p>
            </div>
            <div :class="crmStatCard">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">
                    {{ labels.receivables }}
                </div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-sky-700 dark:text-sky-300">
                    {{ formatMoney(snap.receivables) }}
                </div>
            </div>
            <div :class="crmStatCard">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">
                    {{ labels.payables }}
                </div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-amber-700 dark:text-amber-300">
                    {{ formatMoney(snap.payables) }}
                </div>
            </div>
            <div :class="crmStatCard">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">
                    {{ labels.own_money }}
                </div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">
                    {{ formatMoney(snap.own_money_estimate) }}
                </div>
            </div>
            <div :class="crmStatCard">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">
                    {{ labels.borrowed }}
                </div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-rose-700 dark:text-rose-300">
                    {{ formatMoney(snap.borrowed_estimate) }}
                </div>
            </div>
            <div :class="crmStatCard">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">
                    {{ labels.liquidity }}
                </div>
                <div class="mt-1 space-y-1 text-sm">
                    <div>
                        Касса / КЗ:
                        <span class="font-semibold tabular-nums">{{ formatRatio(liquidity.cash_to_payables) }}</span>
                    </div>
                    <div>
                        (Касса+ДЗ) / КЗ:
                        <span class="font-semibold tabular-nums">{{ formatRatio(liquidity.cash_plus_ar_to_payables) }}</span>
                    </div>
                </div>
                <p class="mt-1 text-[10px] leading-4 text-zinc-500">{{ liquidity.note }}</p>
            </div>
        </div>

        <section :class="crmPanel" class="space-y-2 p-4">
            <h2 :class="crmSectionTitle">Что дальше</h2>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Детализация по контрагентам — в
                <Link
                    :href="balance.working_capital_url"
                    class="font-medium text-sky-700 hover:underline dark:text-sky-300"
                >
                    Оборотном капитале
                </Link>
                . Полный бух. баланс с двойной записью — вне scope (ADR-025 Out).
            </p>
            <p class="text-[11px] text-zinc-500">
                База признания упр. ОПиУ (parked):
                <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">{{ balance.pnl_recognition_basis }}</code>
            </p>
        </section>
    </div>
</template>
