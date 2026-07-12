<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    trialDays: { type: Number, default: 14 },
});

const form = useForm({
    company_name: '',
    admin_name: '',
    admin_email: '',
});

function submit() {
    form.post(route('demo.signup.store'));
}
</script>

<template>
    <div class="min-h-dvh bg-[#0B1220] text-slate-100">
        <Head title="Демо-доступ Traklo Pro" />

        <div class="mx-auto flex max-w-lg flex-col gap-6 px-4 py-16 sm:px-6">
            <div class="space-y-2 text-center">
                <h1 class="text-2xl font-semibold text-white">Демо-доступ Traklo Pro</h1>
                <p class="text-sm text-slate-400">
                    Пробный период {{ trialDays }} дней · тариф Start · оплата по безналу после пилота
                </p>
            </div>

            <form class="space-y-4 rounded-2xl border border-white/10 bg-white/5 p-6" @submit.prevent="submit">
                <div class="space-y-1">
                    <label class="text-xs uppercase tracking-wide text-slate-400">Компания</label>
                    <input
                        v-model="form.company_name"
                        type="text"
                        class="w-full rounded-xl border border-white/10 bg-[#0B1220] px-3 py-2 text-sm"
                        placeholder="ООО «Моя экспедиция»"
                    />
                    <p v-if="form.errors.company_name" class="text-xs text-rose-400">{{ form.errors.company_name }}</p>
                </div>
                <div class="space-y-1">
                    <label class="text-xs uppercase tracking-wide text-slate-400">ФИО администратора</label>
                    <input v-model="form.admin_name" type="text" class="w-full rounded-xl border border-white/10 bg-[#0B1220] px-3 py-2 text-sm" />
                    <p v-if="form.errors.admin_name" class="text-xs text-rose-400">{{ form.errors.admin_name }}</p>
                </div>
                <div class="space-y-1">
                    <label class="text-xs uppercase tracking-wide text-slate-400">Email</label>
                    <input v-model="form.admin_email" type="email" class="w-full rounded-xl border border-white/10 bg-[#0B1220] px-3 py-2 text-sm" />
                    <p v-if="form.errors.admin_email" class="text-xs text-rose-400">{{ form.errors.admin_email }}</p>
                </div>
                <button
                    type="submit"
                    class="w-full rounded-xl bg-blue-600 py-3 text-sm font-medium text-white hover:bg-blue-500 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Получить демо-доступ
                </button>
            </form>
        </div>
    </div>
</template>
