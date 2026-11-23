<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <CrmPageHeader
            title="Интеграция"
            lead="Типовые коннекторы 1С и банков. Не путать со «Связями MCP» — там внешний ИИ читает CRM."
        />

        <div
            v-if="!feature_enabled"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
        >
            <div class="flex items-start gap-2 font-medium">
                <Lock class="mt-0.5 h-4 w-4 shrink-0" />
                {{ upsell.title }}
            </div>
            <p class="mt-1 text-amber-900/90 dark:text-amber-100/90">{{ upsell.body }}</p>
        </div>

        <p
            v-else-if="!can_manage"
            class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"
        >
            Просмотр доступен. Менять коннекторы может только администратор тенанта.
        </p>

        <section v-if="feature_enabled" :class="`${crmPanel} space-y-3 p-4`">
            <h2 class="text-sm font-semibold">Подключённые</h2>
            <p v-if="connectors.length === 0" class="text-sm text-zinc-500">Пока ничего не подключено — выберите карточку каталога ниже.</p>
            <div v-else class="space-y-2">
                <div
                    v-for="row in connectors"
                    :key="row.id"
                    class="flex flex-col gap-2 rounded-xl border border-zinc-200 px-3 py-3 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <div class="font-medium">{{ row.label }}</div>
                        <div class="text-xs text-zinc-500">
                            {{ row.adapter_key }}
                            · {{ row.enabled ? 'вкл' : 'выкл' }}
                            · {{ row.runtime_status }}
                            · {{ row.managed_by === 'platform' ? 'платформа' : 'тенант' }}
                            · секреты: {{ row.has_secrets ? 'заданы' : 'нет' }}
                        </div>
                    </div>
                    <div v-if="can_manage && row.managed_by !== 'platform'" class="flex flex-wrap gap-2">
                        <button type="button" :class="crmBtnSecondary" @click="runTest(row)">Проверить</button>
                        <button type="button" :class="crmBtnSecondary" @click="toggleEnabled(row)">
                            {{ row.enabled ? 'Выключить' : 'Включить' }}
                        </button>
                        <button type="button" :class="crmBtnSecondary" class="text-rose-700 dark:text-rose-300" @click="removeConnector(row)">
                            Удалить
                        </button>
                    </div>
                </div>
            </div>
            <p v-if="lastTestMessage" class="text-sm text-zinc-600 dark:text-zinc-300">{{ lastTestMessage }}</p>
        </section>

        <section :class="`${crmPanel} space-y-3 p-4`">
            <h2 class="text-sm font-semibold">Каталог</h2>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="item in catalog"
                    :key="item.key"
                    class="flex flex-col gap-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700"
                >
                    <div class="font-medium">{{ item.label }}</div>
                    <p class="text-xs text-zinc-500">{{ item.description }}</p>
                    <div class="text-xs text-zinc-400">
                        {{ item.auth_model }} · {{ item.runtime_status }}
                        <span v-if="item.docs_open && item.docs_url"> · </span>
                        <a
                            v-if="item.docs_open && item.docs_url"
                            :href="item.docs_url"
                            target="_blank"
                            rel="noopener"
                            class="underline"
                        >документация</a>
                    </div>
                    <button
                        v-if="can_manage"
                        type="button"
                        :class="crmBtnPrimary"
                        class="mt-auto"
                        @click="openAdd(item)"
                    >
                        Подключить
                    </button>
                    <div
                        v-else-if="!feature_enabled"
                        class="mt-auto inline-flex items-center gap-1 text-xs font-medium text-amber-700 dark:text-amber-300"
                    >
                        <Lock class="h-3.5 w-3.5" /> Enterprise
                    </div>
                </article>
            </div>
        </section>

        <div
            v-if="draft"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center"
            @click.self="draft = null"
        >
            <form :class="`${crmPanel} w-full max-w-lg space-y-3 p-4`" @submit.prevent="submitAdd">
                <h3 class="text-base font-semibold">{{ draft.label }}</h3>
                <p class="text-xs text-zinc-500">{{ draft.description }}</p>

                <div v-if="draft.key === 'one_c_bp'" class="space-y-2">
                    <label class="text-sm font-medium">URL публикации OData</label>
                    <input v-model="form.endpoints.base_url" type="url" :class="crmFieldFluid" placeholder="https://…" required>
                    <label class="text-sm font-medium">Логин</label>
                    <input v-model="form.secrets.username" type="text" :class="crmFieldFluid" autocomplete="off">
                    <label class="text-sm font-medium">Пароль</label>
                    <input v-model="form.secrets.password" type="password" :class="crmFieldFluid" autocomplete="new-password">
                </div>

                <div v-else-if="draft.key === 'vtb_bank'" class="space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <p>ВТБ ИБК: после открытия счёта подключите ИБК, ГОСТ TLS и CryptoPro УНЭП. Живой обмен — после спеки банка.</p>
                    <label class="flex items-center gap-2">
                        <input v-model="form.endpoints.onboarding.settlement_account_opened" type="checkbox">
                        Расчётный счёт открыт
                    </label>
                    <label class="flex items-center gap-2">
                        <input v-model="form.endpoints.onboarding.ibk_service_enabled" type="checkbox">
                        Сервис ИБК включён
                    </label>
                </div>

                <div v-else class="space-y-2">
                    <label class="text-sm font-medium">Токен / client secret</label>
                    <input v-model="form.secrets.token" type="password" :class="crmFieldFluid" autocomplete="new-password" placeholder="Bearer / JWT / ApiKey">
                    <label class="text-sm font-medium">Client ID (если нужен)</label>
                    <input v-model="form.secrets.client_id" type="text" :class="crmFieldFluid" autocomplete="off">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" :class="crmBtnSecondary" @click="draft = null">Отмена</button>
                    <button type="submit" :class="crmBtnPrimary" :disabled="form.processing">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { Lock } from 'lucide-vue-next';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnPrimary, crmBtnSecondary, crmFieldFluid, crmPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, {
        activeKey: 'settings',
        activeSubKey: 'configuration',
        activeLeafKey: 'integrations',
    }, () => page),
});

const props = defineProps({
    feature_enabled: { type: Boolean, default: false },
    can_manage: { type: Boolean, default: false },
    catalog: { type: Array, default: () => [] },
    connectors: { type: Array, default: () => [] },
    upsell: { type: Object, default: () => ({ title: '', body: '' }) },
});

const draft = ref(null);
const lastTestMessage = ref('');

const form = useForm({
    adapter_key: '',
    enabled: true,
    endpoints: { base_url: '', onboarding: { settlement_account_opened: false, ibk_service_enabled: false } },
    mapping: {},
    secrets: { token: '', username: '', password: '', client_id: '', client_secret: '' },
});

function openAdd(item) {
    draft.value = item;
    form.adapter_key = item.key;
    form.endpoints = { base_url: '', onboarding: { settlement_account_opened: false, ibk_service_enabled: false } };
    form.secrets = { token: '', username: '', password: '', client_id: '', client_secret: '' };
    form.clearErrors();
}

function submitAdd() {
    const payload = {
        adapter_key: form.adapter_key,
        enabled: true,
        endpoints: form.adapter_key === 'vtb_bank'
            ? { onboarding: { ...form.endpoints.onboarding } }
            : (form.endpoints.base_url ? { base_url: form.endpoints.base_url } : {}),
        secrets: Object.fromEntries(
            Object.entries(form.secrets).filter(([, value]) => String(value || '').trim() !== ''),
        ),
    };

    form.transform(() => payload).post(route('settings.integrations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            draft.value = null;
        },
    });
}

function toggleEnabled(row) {
    router.put(route('settings.integrations.update', row.id), { enabled: !row.enabled }, { preserveScroll: true });
}

function removeConnector(row) {
    if (!window.confirm(`Удалить коннектор «${row.label}»?`)) {
        return;
    }
    router.delete(route('settings.integrations.destroy', row.id), { preserveScroll: true });
}

function runTest(row) {
    lastTestMessage.value = 'Проверка…';
    router.post(route('settings.integrations.test', row.id), {}, {
        preserveScroll: true,
        onSuccess: (page) => {
            lastTestMessage.value = page.props.flash?.message || 'Готово';
        },
        onError: () => {
            lastTestMessage.value = 'Не удалось выполнить проверку';
        },
    });
}
</script>
