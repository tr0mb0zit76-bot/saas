<script setup>
import TrakloLoginScene from '@/Components/Auth/TrakloLoginScene.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
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
                <div>
                    <label for="email" class="sr-only">Email оператора</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="traklo-bar-field"
                        placeholder="Email"
                        required
                        :autofocus="sceneReady || hasValidationErrors"
                        autocomplete="username"
                        @keydown.enter="submit"
                    >
                    <InputError class="mt-1 text-xs text-red-200" :message="form.errors.email" />
                </div>

                <div class="w-[61%]">
                    <label for="password" class="sr-only">Пароль</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="traklo-bar-field"
                        placeholder="Пароль"
                        required
                        autocomplete="current-password"
                        @keydown.enter="submit"
                    >
                    <InputError class="mt-1 text-xs text-red-200" :message="form.errors.password" />
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

<style scoped>
.traklo-bar-field {
    display: block;
    width: 100%;
    height: 2.35rem;
    border-radius: 9999px;
    border: 0;
    background: #ffffff;
    padding: 0 1rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    color: #0f172a;
    box-shadow: 0 1px 2px rgb(15 23 42 / 0.08);
    outline: none;
}

.traklo-bar-field::placeholder {
    color: #94a3b8;
}

.traklo-bar-field:focus {
    box-shadow:
        0 0 0 2px rgb(59 130 246 / 0.55),
        0 1px 2px rgb(15 23 42 / 0.08);
}

@media (min-width: 640px) {
    .traklo-bar-field {
        height: 2.5rem;
    }
}
</style>
