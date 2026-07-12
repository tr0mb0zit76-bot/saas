<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Модули тарифа</h1>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ plan.label }}
                    <span class="font-mono">({{ plan.key }})</span>
                </p>
            </div>
            <Link :href="route('platform.plans.index')" class="text-sm text-sky-600 hover:underline">
                ← К матрице тарифов
            </Link>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-sm font-medium">Лимиты тарифа</h2>
                <p class="mt-1 text-xs text-zinc-500">Пустое поле = без ограничения (∞). Изменения применяются ко всем арендаторам на этом тарифе.</p>
                <div class="mt-3 space-y-3">
                    <div class="space-y-1">
                        <label class="text-xs uppercase tracking-wide text-zinc-500">Пользователи</label>
                        <input
                            v-model="form.limits.users"
                            type="number"
                            min="1"
                            class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            placeholder="∞"
                        />
                        <p v-if="form.errors['limits.users']" class="text-xs text-rose-600">{{ form.errors['limits.users'] }}</p>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs uppercase tracking-wide text-zinc-500">Заказы / месяц</label>
                        <input
                            v-model="form.limits.orders_per_month"
                            type="number"
                            min="1"
                            class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            placeholder="∞"
                        />
                        <p v-if="form.errors['limits.orders_per_month']" class="text-xs text-rose-600">{{ form.errors['limits.orders_per_month'] }}</p>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs uppercase tracking-wide text-zinc-500">Хранилище (МБ)</label>
                        <input
                            v-model="form.limits.storage_mb"
                            type="number"
                            min="1"
                            class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            placeholder="∞"
                        />
                        <p v-if="form.errors['limits.storage_mb']" class="text-xs text-rose-600">{{ form.errors['limits.storage_mb'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div
                v-for="group in groupedFeatures"
                :key="group.name"
                class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"
            >
                <h2 class="text-sm font-medium">{{ group.name }}</h2>
                <ul class="mt-3 space-y-2">
                    <li v-for="feature in group.items" :key="feature.key" class="flex items-center justify-between gap-4 text-sm">
                        <div>{{ feature.label }}</div>
                        <label class="inline-flex items-center gap-2">
                            <input v-model="form.features[feature.key]" type="checkbox" class="rounded border-zinc-300" />
                            <span>{{ form.features[feature.key] ? 'Вкл' : 'Выкл' }}</span>
                        </label>
                    </li>
                </ul>
            </div>

            <button
                type="submit"
                class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-50"
                :disabled="form.processing"
            >
                Сохранить тариф
            </button>
        </form>
    </div>
</template>

<script setup>
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({
    layout: (h, page) => h(PlatformLayout, { activeKey: 'plans' }, () => page),
});

const props = defineProps({
    plan: { type: Object, required: true },
    features: { type: Array, default: () => [] },
});

const initialFeatures = Object.fromEntries(
    props.features.map((feature) => [feature.key, Boolean(feature.enabled)]),
);

const initialLimits = {
    users: props.plan.limits?.users ?? '',
    orders_per_month: props.plan.limits?.orders_per_month ?? '',
    storage_mb: props.plan.limits?.storage_mb ?? '',
};

const form = useForm({
    features: initialFeatures,
    limits: initialLimits,
});

const groupedFeatures = computed(() => {
    const groups = new Map();

    for (const feature of props.features) {
        const name = feature.group_label || feature.group;
        if (!groups.has(name)) {
            groups.set(name, { name, items: [] });
        }
        groups.get(name).items.push(feature);
    }

    return [...groups.values()];
});

function submit() {
    form.transform((data) => ({
        ...data,
        limits: {
            users: data.limits.users === '' ? null : data.limits.users,
            orders_per_month: data.limits.orders_per_month === '' ? null : data.limits.orders_per_month,
            storage_mb: data.limits.storage_mb === '' ? null : data.limits.storage_mb,
        },
    })).patch(route('platform.plans.features.update', props.plan.key), {
        preserveScroll: true,
    });
}
</script>
