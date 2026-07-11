<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Тарифы и модули</h1>
            <p class="mt-1 text-sm text-zinc-500">Каталог модулей подписки по тарифам Start / Pro / Enterprise.</p>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <table class="min-w-full text-sm">
                <thead class="border-b border-zinc-200 text-left text-xs uppercase tracking-wide text-zinc-500 dark:border-zinc-800">
                    <tr>
                        <th class="px-3 py-3">Модуль</th>
                        <th class="px-3 py-3">Группа</th>
                        <th v-for="plan in plans" :key="plan.key" class="px-3 py-3 text-center">{{ plan.label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in matrix" :key="row.key" class="border-b border-zinc-100 dark:border-zinc-900">
                        <td class="px-3 py-2">{{ row.label }}</td>
                        <td class="px-3 py-2 text-zinc-500">{{ row.group_label }}</td>
                        <td v-for="plan in plans" :key="plan.key" class="px-3 py-2 text-center">
                            <span v-if="row.plans[plan.key]" class="text-emerald-600">✓</span>
                            <span v-else class="text-zinc-300">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div v-for="plan in plans" :key="plan.key" class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-medium">{{ plan.label }}</h2>
                    <Link
                        :href="route('platform.plans.edit', plan.key)"
                        class="rounded-lg border border-zinc-200 px-2 py-1 text-xs text-sky-700 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                    >
                        Модули
                    </Link>
                </div>
                <dl class="mt-3 space-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                    <div class="flex justify-between"><dt>Пользователи</dt><dd>{{ formatLimit(plan.limits.users) }}</dd></div>
                    <div class="flex justify-between"><dt>Заказы/мес</dt><dd>{{ formatLimit(plan.limits.orders_per_month) }}</dd></div>
                    <div class="flex justify-between"><dt>Хранилище</dt><dd>{{ formatStorage(plan.limits.storage_mb) }}</dd></div>
                </dl>
            </div>
        </div>
    </div>
</template>

<script setup>
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Link } from '@inertiajs/vue3';

defineOptions({
    layout: (h, page) => h(PlatformLayout, { activeKey: 'plans' }, () => page),
});

defineProps({
    plans: { type: Array, default: () => [] },
    matrix: { type: Array, default: () => [] },
    groups: { type: Object, default: () => ({}) },
});

function formatLimit(value) {
    return value == null ? '∞' : String(value);
}

function formatStorage(mb) {
    if (mb == null) {
        return '∞';
    }

    return mb >= 1024 ? `${Math.round(mb / 1024)} GB` : `${mb} MB`;
}
</script>
