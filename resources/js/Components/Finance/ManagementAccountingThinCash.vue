<template>
    <section class="space-y-4">
        <div :class="`${crmPanel} p-4`">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                {{ cash.label || 'Касса' }}
            </h2>
            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                {{ cash.disclaimer }}
            </p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article :class="`${crmStatCard} p-4`">
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Сейчас</div>
                    <div class="mt-2 text-right text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-50">
                        {{ formatMoney(cash.balance_now) }}
                    </div>
                    <div class="mt-1 text-right text-[10px] text-zinc-500">на {{ cash.as_of }}</div>
                </article>
                <article :class="`${crmStatCard} p-4`">
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Стартовое сальдо</div>
                    <div class="mt-2 text-right text-lg font-semibold tabular-nums">{{ formatMoney(cash.opening_balance) }}</div>
                    <div class="mt-1 text-right text-[10px] text-zinc-500">{{ openingSourceLabel }}</div>
                </article>
                <article :class="`${crmStatCard} p-4`">
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Приход (обороты)</div>
                    <div class="mt-2 text-right text-lg font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">
                        {{ formatMoney(cash.turnover_in) }}
                    </div>
                    <div class="mt-1 text-right text-[10px] text-zinc-500">
                        банк {{ formatMoney(cash.bank_in) }} · ручн. {{ formatMoney(cash.manual_in) }}
                    </div>
                </article>
                <article :class="`${crmStatCard} p-4`">
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Расход (обороты)</div>
                    <div class="mt-2 text-right text-lg font-semibold tabular-nums text-rose-700 dark:text-rose-300">
                        {{ formatMoney(cash.turnover_out) }}
                    </div>
                    <div class="mt-1 text-right text-[10px] text-zinc-500">
                        банк {{ formatMoney(cash.bank_out) }} · ручн. {{ formatMoney(cash.manual_out) }}
                    </div>
                </article>
            </div>
        </div>

        <div :class="`${crmPanel} p-4`">
            <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">Стартовое сальдо счёта</h3>
            <p class="mt-1 text-xs text-zinc-500">
                Из выписки или ручной override — оба варианта. Обороты после даты старта прибавляются к сальдо.
            </p>

            <form class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="saveOpening">
                <label class="space-y-1">
                    <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Счёт</span>
                    <select v-model="form.bank_account_id" :class="fieldClass" required>
                        <option v-for="account in bankAccounts" :key="account.id" :value="account.id">
                            {{ account.bank_name }} · {{ account.account_mask || '—' }}
                        </option>
                    </select>
                </label>
                <label class="space-y-1">
                    <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Сумма</span>
                    <input v-model.number="form.opening_balance" type="number" step="0.01" :class="fieldClass" required>
                </label>
                <label class="space-y-1">
                    <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">На дату</span>
                    <input v-model="form.opening_balance_as_of" type="date" :class="fieldClass">
                </label>
                <label class="space-y-1">
                    <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Источник</span>
                    <select v-model="form.opening_balance_source" :class="fieldClass">
                        <option value="manual">Ручной ввод</option>
                        <option value="statement">Из выписки</option>
                    </select>
                </label>
                <div class="sm:col-span-2 lg:col-span-4">
                    <button type="submit" :class="crmBtnPrimary" :disabled="form.processing">
                        Сохранить сальдо
                    </button>
                </div>
            </form>
        </div>
    </section>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { crmBtnPrimary, crmPanel, crmStatCard } from '@/support/crmUi.js';

const props = defineProps({
    cash: { type: Object, required: true },
    bankAccounts: { type: Array, default: () => [] },
});

const fieldClass = 'w-full rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-950';

const form = useForm({
    bank_account_id: props.bankAccounts[0]?.id ?? null,
    opening_balance: props.bankAccounts[0]?.opening_balance ?? 0,
    opening_balance_as_of: props.bankAccounts[0]?.opening_balance_as_of ?? '',
    opening_balance_source: props.bankAccounts[0]?.opening_balance_source || 'manual',
});

watch(
    () => form.bank_account_id,
    (id) => {
        const account = props.bankAccounts.find((row) => row.id === id);
        if (!account) {
            return;
        }
        form.opening_balance = account.opening_balance ?? 0;
        form.opening_balance_as_of = account.opening_balance_as_of ?? '';
        form.opening_balance_source = account.opening_balance_source || 'manual';
    },
);

const openingSourceLabel = computed(() => {
    switch (props.cash.opening_source) {
        case 'manual':
            return 'ручной override';
        case 'statement':
            return 'из выписки';
        case 'mixed':
            return 'смешанный';
        default:
            return 'не задано (0)';
    }
});

function saveOpening() {
    form.patch(route('finance.management-accounting.thin-cash.opening-balance'), {
        preserveScroll: true,
    });
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 0,
    }).format(Number(value) || 0);
}
</script>
