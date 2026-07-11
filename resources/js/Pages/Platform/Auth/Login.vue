<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('platform.login.store'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-zinc-950 px-4 py-12 text-zinc-100">
        <Head title="Вход в платформу" />

        <div class="w-full max-w-md rounded-2xl border border-zinc-800 bg-zinc-900 p-8 shadow-xl">
            <div class="mb-8 space-y-2">
                <div class="text-xs uppercase tracking-wide text-sky-400">Traklo Pro</div>
                <h1 class="text-2xl font-semibold">Platform Admin</h1>
                <p class="text-sm leading-6 text-zinc-400">
                    Управление арендаторами, тарифами и модулями платформы.
                </p>
            </div>

            <div v-if="status" class="mb-4 rounded-lg border border-emerald-800 bg-emerald-950 px-4 py-3 text-sm text-emerald-200">
                {{ status }}
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <InputLabel for="email" value="Email оператора" class="text-zinc-300" />

                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full border-zinc-700 bg-zinc-950 text-zinc-100"
                        required
                        autofocus
                        autocomplete="username"
                    />

                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="password" value="Пароль" class="text-zinc-300" />

                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full border-zinc-700 bg-zinc-950 text-zinc-100"
                        required
                        autocomplete="current-password"
                    />

                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <PrimaryButton class="w-full justify-center" :disabled="form.processing">
                    Войти
                </PrimaryButton>
            </form>
        </div>
    </div>
</template>
