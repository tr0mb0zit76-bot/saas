<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Модули арендатора</h1>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ tenant.name }}
                    <span class="font-mono">({{ tenant.slug }})</span>
                    · тариф {{ tenant.plan }}
                </p>
            </div>
            <Link :href="route('platform.tenants.index')" class="text-sm text-sky-600 hover:underline">
                ← К списку арендаторов
            </Link>
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
                        <div>
                            <div>{{ feature.label }}</div>
                            <div class="text-xs text-zinc-500">
                                <span v-if="feature.override === null">По тарифу: {{ feature.in_plan ? 'включено' : 'выключено' }}</span>
                                <span v-else class="text-amber-600">Переопределение</span>
                            </div>
                        </div>
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
                Сохранить модули
            </button>
        </form>
    </div>
</template>

<script setup>
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({
    layout: (h, page) => h(PlatformLayout, { activeKey: 'tenants' }, () => page),
});

const props = defineProps({
    tenant: { type: Object, required: true },
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
    form.patch(route('platform.tenants.features.update', props.tenant.id), {
        preserveScroll: true,
    });
}
</script>
