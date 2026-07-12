<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import TrakloLoginScene from '@/Components/Auth/TrakloLoginScene.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TrakloGuestLayout from '@/Layouts/TrakloGuestLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
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

const title = 'Вход в кабинет';
const subtitle = page.props.tenant?.name
    ? `Рабочее пространство «${page.props.tenant.name}»`
    : 'Email и пароль, выданные администратором';
</script>

<template>
    <TrakloGuestLayout>
        <Head title="Вход" />

        <TrakloLoginScene
            v-model:ready="sceneReady"
            :title="title"
            :subtitle="subtitle"
            :instant="hasValidationErrors"
        >
            <template #email>
                <label for="email" class="sr-only">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="traklo-bar-input"
                    placeholder="Email"
                    required
                    :autofocus="sceneReady || hasValidationErrors"
                    autocomplete="username"
                    @keydown.enter="submit"
                >
                <InputError class="traklo-bar-error" :message="form.errors.email" />
            </template>

            <template #password>
                <label for="password" class="sr-only">Пароль</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="traklo-bar-input"
                    placeholder="Пароль"
                    required
                    autocomplete="current-password"
                    @keydown.enter="submit"
                >
                <InputError class="traklo-bar-error" :message="form.errors.password" />
            </template>

            <template #footer>
                <div v-if="status" class="mb-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200">
                    {{ status }}
                </div>

                <form @submit.prevent="submit">
                    <label class="mb-3 flex items-center gap-2.5">
                        <Checkbox v-model:checked="form.remember" name="remember" />
                        <span class="text-sm text-slate-400">Запомнить меня</span>
                    </label>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-sm text-slate-500 underline decoration-slate-600 underline-offset-4 hover:text-slate-300"
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
            </template>
        </TrakloLoginScene>
    </TrakloGuestLayout>
</template>

<style scoped>
/* Transparent field — white bar of the icon is the chrome */
.traklo-bar-input {
    display: block;
    width: 100%;
    height: 100%;
    margin: 0;
    border: 0;
    border-radius: 9999px;
    background: transparent;
    padding: 0 0.85rem;
    font-size: clamp(0.65rem, 2.6vw, 0.8rem);
    line-height: 1;
    color: #0f172a;
    outline: none;
    caret-color: #1d4ed8;
}

.traklo-bar-input::placeholder {
    color: rgb(100 116 139 / 0.85);
}

.traklo-bar-input:focus {
    background: rgb(255 255 255 / 0.35);
}

.traklo-bar-error {
    position: absolute;
    left: 0;
    top: calc(100% + 2px);
    margin: 0;
    font-size: 0.65rem;
    color: #fecaca;
    white-space: nowrap;
}
</style>
