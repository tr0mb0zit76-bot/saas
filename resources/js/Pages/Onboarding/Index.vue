<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { useForm } from '@inertiajs/vue3';

defineOptions({
    layout: (h, page) => h(CrmLayout, {}, () => page),
});

const props = defineProps({
    companyName: { type: String, default: '' },
    defaultTimezone: { type: String, default: 'Europe/Moscow' },
    timezones: { type: Array, default: () => [] },
});

const form = useForm({
    company_name: props.companyName,
    inn: '',
    timezone: props.defaultTimezone,
    sample_customer_name: '',
});

function submit() {
    form.post(route('onboarding.store'));
}
</script>

<template>
    <div class="mx-auto flex min-h-[70vh] max-w-xl flex-col justify-center gap-6 px-4 py-10">
        <div>
            <h1 class="text-2xl font-semibold">Добро пожаловать в Traklo Pro</h1>
            <p class="mt-2 text-sm text-zinc-500">Настройте компанию за минуту — затем можно работать с лидами и заказами.</p>
        </div>

        <form class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900" @submit.prevent="submit">
            <div class="space-y-1">
                <label class="text-xs uppercase tracking-wide text-zinc-500">Ваше юрлицо (own company)</label>
                <input v-model="form.company_name" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                <p v-if="form.errors.company_name" class="text-xs text-rose-600">{{ form.errors.company_name }}</p>
            </div>
            <div class="space-y-1">
                <label class="text-xs uppercase tracking-wide text-zinc-500">ИНН (необязательно)</label>
                <input v-model="form.inn" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
            </div>
            <div class="space-y-1">
                <label class="text-xs uppercase tracking-wide text-zinc-500">Часовой пояс</label>
                <select v-model="form.timezone" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                    <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                </select>
                <p v-if="form.errors.timezone" class="text-xs text-rose-600">{{ form.errors.timezone }}</p>
            </div>
            <div class="space-y-1">
                <label class="text-xs uppercase tracking-wide text-zinc-500">Пример заказчика (необязательно)</label>
                <input
                    v-model="form.sample_customer_name"
                    type="text"
                    class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                    placeholder="ООО «Первый клиент»"
                />
            </div>
            <button type="submit" class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-50" :disabled="form.processing">
                Начать работу
            </button>
        </form>
    </div>
</template>
