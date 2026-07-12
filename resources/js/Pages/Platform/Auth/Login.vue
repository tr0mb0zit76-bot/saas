<script setup>
import TrakloLoginScene from '@/Components/Auth/TrakloLoginScene.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TrakloGuestLayout from '@/Layouts/TrakloGuestLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    status: {
        type: String,
    },
});

const page = usePage();
const sceneReady = ref(false);
const hasValidationErrors = computed(() => Object.keys(page.props.errors ?? {}).length > 0);

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

const barInputClass =
    'block h-9 w-full rounded-full border-0 bg-white/95 px-3.5 text-sm text-slate-800 shadow-sm ring-1 ring-white/80 placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-blue-400 sm:h-10 sm:px-4';
</script>

<template>
    <TrakloGuestLayout>
        <Head title="Вход в платформу" />

        <TrakloLoginScene
            v-model:ready="sceneReady"
            title="Platform Admin"
            subtitle="Управление арендаторами, тарифами и модулями"
            :instant="hasValidationErrors"
        >
            <template #bars>
                <div class="space-y-[0.55rem] sm:space-y-[0.65rem]">
                    <div>
                        <label for="email" class="sr-only">Email оператора</label>
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            :class="barInputClass"
                            placeholder="Email"
                            required
                            :autofocus="sceneReady || hasValidationErrors"
                            autocomplete="username"
                            @keydown.enter="submit"
                        />
                        <InputError class="mt-1 text-xs text-red-200" :message="form.errors.email" />
                    </div>

                    <div class="max-w-[62%]">
                        <label for="password" class="sr-only">Пароль</label>
                        <TextInput
                            id="password"
                            v-model="form.password"
                            type="password"
                            :class="barInputClass"
                            placeholder="Пароль"
                            required
                            autocomplete="current-password"
                            @keydown.enter="submit"
                        />
                        <InputError class="mt-1 text-xs text-red-200" :message="form.errors.password" />
                    </div>
                </div>
            </template>

            <template #footer>
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-blue-400">
                    Operator portal
                </div>

                <div v-if="status" class="mb-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200">
                    {{ status }}
                </div>

                <form @submit.prevent="submit">
                    <PrimaryButton
                        class="w-full justify-center bg-blue-600 hover:bg-blue-500"
                        :disabled="form.processing"
                    >
                        Войти
                    </PrimaryButton>
                </form>
            </template>
        </TrakloLoginScene>
    </TrakloGuestLayout>
</template>
