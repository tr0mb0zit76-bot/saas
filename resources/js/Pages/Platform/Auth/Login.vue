<script setup>
import TrakloLoginScene from '@/Components/Auth/TrakloLoginScene.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
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
</script>

<template>
    <TrakloGuestLayout>
        <Head title="Вход в платформу" />

        <TrakloLoginScene
            v-model:ready="sceneReady"
            title="Platform Admin"
            subtitle="Управление арендаторами, тарифами и модулями платформы"
            :instant="hasValidationErrors"
        >
            <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-blue-600">
                Operator portal
            </div>

            <div v-if="status" class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ status }}
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel for="email" value="Email оператора" class="text-slate-700" />

                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full border-slate-200 bg-white text-slate-900 focus:border-blue-500 focus:ring-blue-500"
                        required
                        :autofocus="sceneReady || hasValidationErrors"
                        autocomplete="username"
                    />

                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="password" value="Пароль" class="text-slate-700" />

                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full border-slate-200 bg-white text-slate-900 focus:border-blue-500 focus:ring-blue-500"
                        required
                        autocomplete="current-password"
                    />

                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <PrimaryButton
                    class="w-full justify-center bg-blue-600 hover:bg-blue-500"
                    :disabled="form.processing"
                >
                    Войти
                </PrimaryButton>
            </form>
        </TrakloLoginScene>
    </TrakloGuestLayout>
</template>
