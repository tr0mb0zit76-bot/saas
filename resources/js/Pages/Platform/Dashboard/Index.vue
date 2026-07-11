<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Обзор платформы</h1>
            <p class="mt-1 text-sm text-zinc-500">Сводка по арендаторам, пользователям и trial-периодам.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="card in statCards" :key="card.label" class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="text-xs uppercase tracking-wide text-zinc-500">{{ card.label }}</div>
                <div class="mt-2 text-3xl font-semibold">{{ card.value }}</div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-sm font-medium">По статусу</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li v-for="(count, status) in stats.by_status" :key="status" class="flex justify-between">
                        <span>{{ statusLabels[status] || status }}</span>
                        <span class="font-medium">{{ count }}</span>
                    </li>
                </ul>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-sm font-medium">По тарифу</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li v-for="plan in planOptions" :key="plan.key" class="flex justify-between">
                        <span>{{ plan.label }}</span>
                        <span class="font-medium">{{ stats.by_plan[plan.key] || 0 }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <div v-if="stats.trials_expiring_soon.length" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40">
            <h2 class="text-sm font-medium text-amber-900 dark:text-amber-200">Trial истекает в 7 дней</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li v-for="tenant in stats.trials_expiring_soon" :key="tenant.id" class="flex justify-between gap-4">
                    <span>{{ tenant.name }} <span class="font-mono text-xs text-zinc-500">({{ tenant.slug }})</span></span>
                    <span>{{ tenant.trial_ends_at }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { computed } from 'vue';

defineOptions({
    layout: (h, page) => h(PlatformLayout, { activeKey: 'dashboard' }, () => page),
});

const props = defineProps({
    stats: { type: Object, required: true },
    planOptions: { type: Array, default: () => [] },
});

const statusLabels = {
    active: 'Активен',
    trial: 'Пробный период',
    suspended: 'Приостановлен',
};

const statCards = computed(() => [
    { label: 'Арендаторы', value: props.stats.tenants_total },
    { label: 'Пользователи', value: props.stats.users_total },
    { label: 'Активные', value: props.stats.by_status?.active || 0 },
    { label: 'Trial', value: props.stats.by_status?.trial || 0 },
]);
</script>
