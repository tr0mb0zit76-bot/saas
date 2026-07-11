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
                <dl class="mt-3 space-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                    <div class="flex justify-between"><dt>Пользователи</dt><dd>{{ formatLimit(plan.limits?.users) }}</dd></div>
                    <div class="flex justify-between"><dt>Заказы/мес</dt><dd>{{ formatLimit(plan.limits?.orders_per_month) }}</dd></div>
                    <div class="flex justify-between"><dt>Хранилище</dt><dd>{{ formatStorage(plan.limits?.storage_mb) }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-zinc-500">Лимиты пока задаются при первичном сиде; редактирование — в следующей итерации.</p>
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
                Сохранить состав тарифа
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

const form = useForm({ features: initialFeatures });

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
    form.patch(route('platform.plans.features.update', props.plan.key), {
        preserveScroll: true,
    });
}

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
