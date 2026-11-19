<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Ops арендатора</h1>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ tenant.name }}
                    <span class="font-mono">({{ tenant.slug }})</span>
                    · тариф {{ tenant.plan }}
                </p>
                <p class="mt-2 max-w-2xl text-sm text-zinc-500">
                    Печать (QR), пакеты книги продаж и коннекторы интеграций — только platform admin, не self-serve клиента.
                </p>
            </div>
            <Link :href="route('platform.tenants.index')" class="text-sm text-sky-600 hover:underline">
                ← К списку арендаторов
            </Link>
        </div>

        <section class="space-y-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-sm font-medium">Проверка печатных документов</h2>
            <p class="text-xs text-zinc-500">
                `settings.print.document_verification` — не модуль тарифа. Выкл = без QR/кода, публичная проверка 404.
            </p>
            <label class="inline-flex items-center gap-2 text-sm">
                <input v-model="printForm.document_verification" type="checkbox" class="rounded border-zinc-300" />
                <span>Включить проверку (QR / уникальный код)</span>
            </label>
            <div>
                <button
                    type="button"
                    class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-50"
                    :disabled="printForm.processing"
                    @click="savePrint"
                >
                    Сохранить проверку
                </button>
            </div>
        </section>

        <section class="space-y-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-sm font-medium">Пакеты книги продаж</h2>
            <ul class="space-y-2 text-sm">
                <li v-for="pack in available_packs" :key="pack.slug" class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <span class="font-mono text-xs">{{ pack.slug }}</span>
                        <span v-if="pack.installed" class="ml-2 text-xs text-emerald-600">установлен</span>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-zinc-200 px-2 py-1 text-xs dark:border-zinc-700"
                        :disabled="packForm.processing"
                        @click="installPack(pack.slug)"
                    >
                        {{ pack.installed ? 'Переустановить' : 'Установить' }}
                    </button>
                </li>
            </ul>
        </section>

        <section class="space-y-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-sm font-medium">Коннекторы (feature:integrations)</h2>
            <p v-if="!integrations_enabled" class="text-sm text-amber-700">
                Модуль интеграций выключен у этого арендатора — сначала включите в «Модули» / тарифе.
            </p>
            <template v-else>
                <div v-if="connectors.length" class="space-y-2 text-sm">
                    <div
                        v-for="row in connectors"
                        :key="row.id"
                        class="rounded-xl border border-zinc-100 px-3 py-2 dark:border-zinc-800"
                    >
                        <div class="font-mono text-xs">{{ row.adapter_key }} · {{ row.id }}</div>
                        <div class="text-xs text-zinc-500">
                            {{ row.enabled ? 'вкл' : 'выкл' }}
                            · секреты: {{ row.has_secrets ? row.secret_keys.join(', ') : 'нет' }}
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-zinc-500">Коннекторов пока нет.</p>

                <form class="grid gap-3 md:grid-cols-2" @submit.prevent="saveConnector">
                    <div class="space-y-1">
                        <label class="text-xs uppercase tracking-wide text-zinc-500">Adapter</label>
                        <select v-model="connectorForm.connector.adapter_key" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                            <option v-for="adapter in adapters" :key="adapter.key" :value="adapter.key">
                                {{ adapter.label }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs uppercase tracking-wide text-zinc-500">Base URL</label>
                        <input v-model="connectorForm.connector.base_url" type="url" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs uppercase tracking-wide text-zinc-500">Username</label>
                        <input v-model="connectorForm.connector.username" type="text" autocomplete="off" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs uppercase tracking-wide text-zinc-500">Password</label>
                        <input v-model="connectorForm.connector.password" type="password" autocomplete="new-password" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm md:col-span-2">
                        <input v-model="connectorForm.connector.enabled" type="checkbox" class="rounded border-zinc-300" />
                        <span>Включён</span>
                    </label>
                    <div class="flex flex-wrap gap-2 md:col-span-2">
                        <button
                            type="submit"
                            class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-50"
                            :disabled="connectorForm.processing"
                        >
                            Привязать / обновить
                        </button>
                        <button
                            type="button"
                            class="rounded-xl border border-zinc-200 px-4 py-2 text-sm dark:border-zinc-700"
                            :disabled="connectorForm.processing"
                            @click="cloneAa"
                        >
                            Клонировать шаблон AA → этот tenant
                        </button>
                    </div>
                </form>
            </template>
        </section>
    </div>
</template>

<script setup>
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

defineOptions({
    layout: (h, page) => h(PlatformLayout, { activeKey: 'tenants' }, () => page),
});

const props = defineProps({
    tenant: { type: Object, required: true },
    document_verification: { type: Boolean, default: false },
    available_packs: { type: Array, default: () => [] },
    installed_packs: { type: Array, default: () => [] },
    integrations_enabled: { type: Boolean, default: false },
    adapters: { type: Array, default: () => [] },
    connectors: { type: Array, default: () => [] },
});

const printForm = useForm({
    document_verification: Boolean(props.document_verification),
});

const packForm = useForm({
    install_pack: null,
    reset_pack: false,
});

const connectorForm = useForm({
    connector: {
        adapter_key: props.adapters[0]?.key || 'one_c_bp',
        enabled: true,
        base_url: '',
        username: '',
        password: '',
        clone_aa_template: false,
        id: null,
    },
});

function savePrint() {
    printForm.patch(route('platform.tenants.ops.update', props.tenant.id), { preserveScroll: true });
}

function installPack(slug) {
    packForm.install_pack = slug;
    packForm.reset_pack = false;
    packForm.patch(route('platform.tenants.ops.update', props.tenant.id), { preserveScroll: true });
}

function saveConnector() {
    connectorForm.connector.clone_aa_template = false;
    connectorForm.patch(route('platform.tenants.ops.update', props.tenant.id), {
        preserveScroll: true,
        onSuccess: () => {
            connectorForm.connector.password = '';
        },
    });
}

function cloneAa() {
    connectorForm.connector.clone_aa_template = true;
    connectorForm.connector.adapter_key = 'one_c_bp';
    connectorForm.patch(route('platform.tenants.ops.update', props.tenant.id), {
        preserveScroll: true,
        onFinish: () => {
            connectorForm.connector.clone_aa_template = false;
        },
    });
}
</script>
