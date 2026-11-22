<script setup>
import InputError from '@/Components/InputError.vue';
import TrakloGuestLayout from '@/Layouts/TrakloGuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    crmDomain: {
        type: String,
        default: '',
    },
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    slug: '',
});

const hostHint = computed(() => {
    const domain = (props.crmDomain || '').trim().toLowerCase();
    if (!domain || ['localhost', '127.0.0.1', 'saas.local'].includes(domain)) {
        return null;
    }

    const slug = form.slug.trim().toLowerCase() || 'компания';

    return `${slug}.${domain}`;
});

const submit = () => {
    form.post(route('login.tenant'));
};
</script>

<template>
    <TrakloGuestLayout>
        <Head title="Вход" />

        <div class="w-full max-w-md space-y-6">
            <div class="space-y-2 text-center sm:text-left">
                <h1 class="text-2xl font-semibold tracking-tight text-white">
                    Вход в кабинет
                </h1>
                <p class="text-sm leading-6 text-slate-400">
                    Укажите код компании — так выглядит адрес кабинета:
                    <span
                        v-if="hostHint"
                        class="mt-1 block font-mono text-slate-200"
                    >{{ hostHint }}</span>
                    <span
                        v-else
                        class="mt-1 block text-slate-500"
                    >его присылает администратор при подключении.</span>
                </p>
            </div>

            <div
                v-if="status"
                class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200"
            >
                {{ status }}
            </div>

            <form
                class="space-y-4"
                @submit.prevent="submit"
            >
                <div>
                    <label
                        for="slug"
                        class="mb-1.5 block text-sm font-medium text-slate-300"
                    >Код компании</label>
                    <input
                        id="slug"
                        v-model="form.slug"
                        type="text"
                        inputmode="latin"
                        autocomplete="organization"
                        autocapitalize="none"
                        spellcheck="false"
                        required
                        autofocus
                        placeholder="например orientum"
                        class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-white outline-none placeholder:text-slate-500 focus:border-blue-500"
                        @keydown.enter="submit"
                    >
                    <InputError
                        class="mt-1.5"
                        :message="form.errors.slug"
                    />
                </div>

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    Продолжить
                </button>
            </form>

            <p class="text-center text-xs text-slate-500 sm:text-left">
                Нет кода? Напишите на
                <a
                    href="mailto:hello@traklo.pro"
                    class="text-slate-300 underline-offset-2 hover:underline"
                >hello@traklo.pro</a>
            </p>
        </div>
    </TrakloGuestLayout>
</template>
