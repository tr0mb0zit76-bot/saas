<template>
    <div class="space-y-4">
        <div>
            <h1 class="text-2xl font-semibold">Журнал аудита</h1>
            <p class="mt-1 text-sm text-zinc-500">Действия platform admin и демо-регистрации по арендаторам.</p>
        </div>

        <form class="flex flex-wrap items-end gap-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900" @submit.prevent="applyFilters">
            <div class="space-y-1">
                <label class="text-xs uppercase tracking-wide text-zinc-500">Арендатор</label>
                <select v-model="filterForm.tenant_id" class="min-w-[200px] rounded-xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                    <option value="">Все</option>
                    <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">{{ tenant.name }} ({{ tenant.slug }})</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="text-xs uppercase tracking-wide text-zinc-500">Действие</label>
                <select v-model="filterForm.action" class="min-w-[220px] rounded-xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                    <option value="">Все</option>
                    <option v-for="option in actionOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">Применить</button>
            <button type="button" class="rounded-xl border border-zinc-300 px-4 py-2 text-sm dark:border-zinc-700" @click="resetFilters">Сбросить</button>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <table class="min-w-full text-sm">
                <thead class="border-b border-zinc-200 text-left text-xs uppercase tracking-wide text-zinc-500 dark:border-zinc-800">
                    <tr>
                        <th class="px-3 py-2">Время</th>
                        <th class="px-3 py-2">Действие</th>
                        <th class="px-3 py-2">Арендатор</th>
                        <th class="px-3 py-2">Пользователь</th>
                        <th class="px-3 py-2">Детали</th>
                        <th class="px-3 py-2">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs.data" :key="log.id" class="border-b border-zinc-100 align-top dark:border-zinc-900">
                        <td class="whitespace-nowrap px-3 py-2 text-xs text-zinc-500">{{ formatDate(log.created_at) }}</td>
                        <td class="px-3 py-2">
                            <span class="rounded-lg bg-zinc-100 px-2 py-1 font-mono text-xs dark:bg-zinc-800">{{ log.action }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <template v-if="log.tenant">
                                {{ log.tenant.name }}
                                <span class="font-mono text-xs text-zinc-500">({{ log.tenant.slug }})</span>
                            </template>
                            <span v-else class="text-zinc-400">—</span>
                        </td>
                        <td class="px-3 py-2">
                            <template v-if="log.user">
                                {{ log.user.name }}
                                <div class="text-xs text-zinc-500">{{ log.user.email }}</div>
                            </template>
                            <span v-else class="text-zinc-400">system</span>
                        </td>
                        <td class="max-w-md px-3 py-2">
                            <details v-if="log.old_values || log.new_values" class="text-xs">
                                <summary class="cursor-pointer text-sky-600">JSON</summary>
                                <pre class="mt-2 overflow-x-auto rounded-lg bg-zinc-50 p-2 dark:bg-zinc-950">{{ formatPayload(log) }}</pre>
                            </details>
                            <span v-else class="text-zinc-400">—</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-zinc-500">{{ log.ip_address || '—' }}</td>
                    </tr>
                    <tr v-if="!logs.data.length">
                        <td colspan="6" class="px-3 py-8 text-center text-zinc-500">Записей пока нет.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="logs.links?.length > 3" class="flex flex-wrap gap-2">
            <Link
                v-for="link in logs.links"
                :key="link.label"
                :href="link.url || '#'"
                class="rounded-lg px-3 py-1 text-sm"
                :class="link.active
                    ? 'bg-sky-100 font-medium text-sky-800 dark:bg-sky-950 dark:text-sky-200'
                    : link.url
                        ? 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800'
                        : 'cursor-not-allowed text-zinc-300 dark:text-zinc-700'"
                v-html="link.label"
            />
        </div>
    </div>
</template>

<script setup>
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

defineOptions({
    layout: (h, page) => h(PlatformLayout, { activeKey: 'audit' }, () => page),
});

const props = defineProps({
    logs: { type: Object, required: true },
    tenants: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    actionOptions: { type: Array, default: () => [] },
});

const filterForm = reactive({
    tenant_id: props.filters.tenant_id ?? '',
    action: props.filters.action ?? '',
});

function applyFilters() {
    router.get(route('platform.audit.index'), {
        tenant_id: filterForm.tenant_id || undefined,
        action: filterForm.action || undefined,
    }, { preserveState: true, replace: true });
}

function resetFilters() {
    filterForm.tenant_id = '';
    filterForm.action = '';
    applyFilters();
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('ru-RU');
}

function formatPayload(log) {
    return JSON.stringify({
        old: log.old_values,
        new: log.new_values,
    }, null, 2);
}
</script>
