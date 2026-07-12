<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import TrakloLoginScene from '@/Components/Auth/TrakloLoginScene.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TrakloGuestLayout from '@/Layouts/TrakloGuestLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
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
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <TrakloGuestLayout
        title="Вход в кабинет"
        :subtitle="page.props.tenant?.name ? `Рабочее пространство «${page.props.tenant.name}»` : 'Email и пароль, выданные администратором'"
    >
        <Head title="Вход" />

        <template #scene>
            <TrakloLoginScene
                v-model:ready="sceneReady"
                :instant="hasValidationErrors"
            />
        </template>

        <div
            class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 shadow-xl shadow-black/20 backdrop-blur-sm transition duration-500 sm:p-6"
            :class="sceneReady || hasValidationErrors ? 'translate-y-0 opacity-100' : 'pointer-events-none translate-y-3 opacity-0'"
        >
            <div v-if="status" class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                {{ status }}
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel for="email" value="Email" class="text-slate-300" />

                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full border-white/10 bg-[#111827] text-white placeholder:text-slate-500 focus:border-blue-500 focus:ring-blue-500"
                        required
                        :autofocus="sceneReady || hasValidationErrors"
                        autocomplete="username"
                    />

                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="password" value="Пароль" class="text-slate-300" />

                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full border-white/10 bg-[#111827] text-white placeholder:text-slate-500 focus:border-blue-500 focus:ring-blue-500"
                        required
                        autocomplete="current-password"
                    />

                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <label class="flex items-center gap-3">
                    <Checkbox v-model:checked="form.remember" name="remember" />
                    <span class="text-sm text-slate-400">Запомнить меня</span>
                </label>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-sm text-slate-400 underline decoration-white/20 underline-offset-4 hover:text-white"
                    >
                        Забыли пароль?
                    </Link>
                    <span v-else />

                    <PrimaryButton
                        class="w-full justify-center bg-blue-600 hover:bg-blue-500 sm:w-auto"
                        :class="{ 'opacity-50': form.processing }"
                        :disabled="form.processing"
                    >
                        Войти
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </TrakloGuestLayout>
</template>
