<template>
    <div>
        <button
            v-if="canManage"
            type="button"
            :class="crmBtnPrimary"
            @click="openModal"
        >
            Наличные / карта
        </button>

        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="open = false"
        >
            <form
                :class="`${crmPanel} w-full max-w-lg space-y-4 p-5`"
                @submit.prevent="submit"
            >
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                        Ручной расход или приход
                    </h2>
                    <button type="button" class="text-sm text-zinc-500 hover:text-zinc-800" @click="open = false">
                        Закрыть
                    </button>
                </div>

                <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                    Для оплат наличными или корпоративной картой — то, что нельзя подтянуть из банка.
                </p>

                <div :class="crmModalFieldsWrap">
                    <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                        <label :class="crmModalFieldLabel">Способ</label>
                        <select
                            v-model="instrument"
                            required
                            class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                            @change="syncBankAccount"
                        >
                            <option value="cash">Наличные</option>
                            <option value="corp_card">Корпоративная карта</option>
                            <option value="bank">Расчётный счёт</option>
                        </select>
                    </div>

                    <div :class="crmModalFieldRow">
                        <label :class="crmModalFieldLabel">Дата</label>
                        <input
                            v-model="form.operation_date"
                            type="date"
                            required
                            class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                        >
                    </div>

                    <div
                        v-if="instrument === 'bank'"
                        :class="`${crmModalFieldRow} crm-modal-field-row--full`"
                    >
                        <label :class="crmModalFieldLabel">Счёт</label>
                        <select
                            v-model="form.bank_account_id"
                            required
                            class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                        >
                            <option disabled value="">Выберите счёт</option>
                            <option
                                v-for="account in bankOnlyAccounts"
                                :key="account.id"
                                :value="account.id"
                            >
                                {{ account.bank_name }} · {{ account.account_mask || account.currency }}
                            </option>
                        </select>
                    </div>

                    <div class="flex min-w-full flex-[1_1_100%] flex-wrap items-center gap-x-3 gap-y-2">
                    <div class="flex min-w-full flex-[1_1_100%] flex-wrap items-center gap-x-3 gap-y-2">
                        <div :class="crmModalFieldRow">
                            <label :class="crmModalFieldLabel">Направление</label>
                            <select
                                v-model="form.direction"
                                class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                                @change="ensureCategoryForDirection"
                            >
                                <option value="out">Расход</option>
                                <option value="in">Приход</option>
                            </select>
                        </div>

                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                            <label :class="crmModalFieldLabel">Сумма</label>
                            <input
                                v-model.number="form.amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                required
                                class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm tabular-nums dark:border-zinc-600 dark:bg-zinc-950"
                            >
                        </div>
                    </div>
                    </div>

                    <div :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                        <label :class="crmModalFieldLabel">Статья</label>
                        <select
                            v-model="form.category_id"
                            required
                            class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                        >
                            <option
                                v-for="category in filteredCategories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <label class="block w-full space-y-1">
                    <span :class="crmModalFieldLabel">Описание</span>
                    <textarea
                        v-model="form.description"
                        rows="2"
                        required
                        maxlength="2000"
                        class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                    />
                </label>

                <div class="flex justify-end gap-2">
                    <button type="button" :class="crmBtnNeutral" @click="open = false">Отмена</button>
                    <button type="submit" :class="crmBtnPrimary" :disabled="form.processing">
                        {{ form.processing ? 'Сохранение…' : 'Сохранить' }}
                    </button>
                </div>
            </form>
        </div>

        <div v-if="recentEntries.length > 0" class="mt-4">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Последние ручные операции</h3>
            <ul class="mt-2 divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
                <li
                    v-for="entry in recentEntries"
                    :key="entry.id"
                    class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-xs"
                >
                    <span class="text-zinc-500">{{ formatDate(entry.operation_date) }}</span>
                    <span
                        class="font-semibold tabular-nums"
                        :class="entry.direction === 'in' ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'"
                    >
                        {{ entry.direction === 'in' ? '+' : '−' }}{{ formatMoney(entry.amount) }}
                    </span>
                    <span class="min-w-0 flex-1 truncate text-zinc-600 dark:text-zinc-300">{{ entry.description }}</span>
                    <span class="text-zinc-500">{{ entry.category_name || entry.status }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { crmBtnNeutral, crmBtnPrimary, crmModalFieldLabel, crmModalFieldRow, crmModalFieldsWrap, crmPanel } from '@/support/crmUi.js';

const props = defineProps({
    bankAccounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    recentEntries: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const open = ref(false);
const instrument = ref('cash');

const cashAccount = computed(() => props.bankAccounts.find((a) => a.instrument === 'cash') ?? null);
const cardAccount = computed(() => props.bankAccounts.find((a) => a.instrument === 'corp_card') ?? null);
const bankOnlyAccounts = computed(() =>
    props.bankAccounts.filter((a) => a.instrument === 'bank' || !a.instrument),
);

const form = useForm({
    bank_account_id: cashAccount.value?.id ?? '',
    operation_date: new Date().toISOString().slice(0, 10),
    direction: 'out',
    amount: '',
    currency: 'RUB',
    description: '',
    allocation_type: 'category',
    category_id: null,
    payment_schedule_id: null,
    user_id: null,
});

const categoryById = computed(() => {
    const map = new Map();
    props.categories.forEach((category) => {
        map.set(category.id, category);
    });

    return map;
});

/** Как в отчёте: «АУР → Банковские комиссии», без корня «Расходы»/«Доходы». */
function categoryPathLabel(category) {
    const parts = [category.name];
    let parentId = category.parent_id;

    while (parentId != null) {
        const parent = categoryById.value.get(parentId);
        if (!parent) {
            break;
        }

        if (parent.parent_id == null && parent.kind === 'group') {
            break;
        }

        parts.unshift(parent.name);
        parentId = parent.parent_id;
    }

    return parts.join(' → ');
}

const filteredCategories = computed(() => {
    const flow = form.direction === 'in' ? 'in' : 'out';

    return props.categories
        .filter((category) => category.kind !== 'group' && (category.flow ?? 'out') === flow)
        .map((category) => ({
            ...category,
            label: categoryPathLabel(category),
        }))
        .sort((left, right) => left.label.localeCompare(right.label, 'ru'));
});

function ensureCategoryForDirection() {
    const ids = filteredCategories.value.map((c) => c.id);
    if (!ids.includes(form.category_id)) {
        form.category_id = ids[0] ?? null;
    }
}

function syncBankAccount() {
    if (instrument.value === 'cash') {
        form.bank_account_id = cashAccount.value?.id ?? '';
        return;
    }

    if (instrument.value === 'corp_card') {
        form.bank_account_id = cardAccount.value?.id ?? '';
        return;
    }

    form.bank_account_id = bankOnlyAccounts.value[0]?.id ?? '';
}

function openModal() {
    instrument.value = 'cash';
    syncBankAccount();
    ensureCategoryForDirection();
    open.value = true;
}

watch(
    () => [cashAccount.value?.id, cardAccount.value?.id],
    () => {
        if (!open.value) {
            syncBankAccount();
        }
    },
);

function submit() {
    form.allocation_type = 'category';
    syncBankAccount();
    ensureCategoryForDirection();

    form.post(route('finance.management-accounting.manual-entries.store'), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset('amount', 'description');
            form.allocation_type = 'category';
            instrument.value = 'cash';
            syncBankAccount();
            ensureCategoryForDirection();
        },
    });
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 0,
    }).format(Number(value) || 0);
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('ru-RU');
}

ensureCategoryForDirection();
</script>
