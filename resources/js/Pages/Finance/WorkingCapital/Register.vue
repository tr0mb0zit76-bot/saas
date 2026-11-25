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
        h(CrmLayout, { activeKey: 'finance', activeSubKey: 'finance-working-capital' }, () => page),
});

const props = defineProps({
    register: {
        type: Object,
        required: true,
    },
});

const totals = computed(() => props.register?.totals ?? { receivables: 0, payables: 0, net: 0 });

function formatMoney(value) {
    const n = typeof value === 'number' ? value : Number(value) || 0;
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(n);
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
                <h1 :class="crmPageTitle">{{ register.title }}</h1>
                <p :class="crmPageLead">{{ register.subtitle }}</p>
                <p class="max-w-3xl text-xs leading-5 text-amber-700 dark:text-amber-300">
                    {{ register.disclaimer }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <Link
                    :href="register.balance_url"
                    class="font-medium text-sky-700 hover:underline dark:text-sky-300"
                >
                    Баланс →
                </Link>
                <Link
                    :href="register.cashflow_url"
                    class="font-medium text-sky-700 hover:underline dark:text-sky-300"
                >
                    График оплат →
                </Link>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <div :class="crmStatCard">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Дебиторка</div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-sky-700 dark:text-sky-300">
                    {{ formatMoney(totals.receivables) }}
                </div>
            </div>
            <div :class="crmStatCard">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Кредиторка</div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-amber-700 dark:text-amber-300">
                    {{ formatMoney(totals.payables) }}
                </div>
            </div>
            <div :class="crmStatCard">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Нетто ДЗ−КЗ</div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-50">
                    {{ formatMoney(totals.net) }}
                </div>
            </div>
        </div>

        <section :class="crmPanel" class="space-y-3 p-4">
            <h2 :class="crmSectionTitle">Дебиторка (заказчики)</h2>
            <div v-if="!(register.receivables || []).length" class="text-sm text-zinc-500">Нет открытых ДЗ.</div>
            <table v-else class="w-full text-left text-sm">
                <thead class="text-[10px] uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="pb-2 font-medium">Контрагент</th>
                        <th class="pb-2 font-medium text-right">Строк</th>
                        <th class="pb-2 font-medium text-right">Остаток</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, idx) in register.receivables"
                        :key="'ar-' + (row.contractor_id ?? idx)"
                        class="border-t border-zinc-100 dark:border-zinc-800"
                    >
                        <td class="py-2 text-zinc-900 dark:text-zinc-100">{{ row.contractor_name }}</td>
                        <td class="py-2 text-right tabular-nums text-zinc-500">{{ row.lines_count }}</td>
                        <td class="py-2 text-right font-medium tabular-nums text-sky-700 dark:text-sky-300">
                            {{ formatMoney(row.amount) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section :class="crmPanel" class="space-y-3 p-4">
            <h2 :class="crmSectionTitle">Кредиторка (перевозчики)</h2>
            <div v-if="!(register.payables || []).length" class="text-sm text-zinc-500">Нет открытых КЗ.</div>
            <table v-else class="w-full text-left text-sm">
                <thead class="text-[10px] uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="pb-2 font-medium">Контрагент</th>
                        <th class="pb-2 font-medium text-right">Строк</th>
                        <th class="pb-2 font-medium text-right">Остаток</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, idx) in register.payables"
                        :key="'ap-' + (row.contractor_id ?? idx)"
                        class="border-t border-zinc-100 dark:border-zinc-800"
                    >
                        <td class="py-2 text-zinc-900 dark:text-zinc-100">{{ row.contractor_name }}</td>
                        <td class="py-2 text-right tabular-nums text-zinc-500">{{ row.lines_count }}</td>
                        <td class="py-2 text-right font-medium tabular-nums text-amber-700 dark:text-amber-300">
                            {{ formatMoney(row.amount) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
