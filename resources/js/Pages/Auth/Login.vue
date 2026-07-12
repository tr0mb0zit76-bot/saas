<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import TrakloLoginScene from '@/Components/Auth/TrakloLoginScene.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TrakloGuestLayout from '@/Layouts/TrakloGuestLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const page = usePage();

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
    ? `«${page.props.tenant.name}»`
    : 'Traklo Pro';
</script>

<template>
    <TrakloGuestLayout>
        <Head title="Вход" />

        <TrakloLoginScene :title="title" :subtitle="subtitle">
            <template #email>
                <label for="email" class="sr-only">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="traklo-bar-input"
                    placeholder="Email"
                    required
                    autofocus
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
                <div v-if="status" class="mb-2 rounded-lg bg-emerald-500/20 px-2 py-1 text-xs text-emerald-100">
                    {{ status }}
                </div>

                <form class="traklo-footer-form" @submit.prevent="submit">
                    <div class="traklo-footer-links">
                        <label class="flex items-center gap-2.5">
                            <Checkbox v-model:checked="form.remember" name="remember" />
                            <span class="text-[clamp(0.78rem,1.9vw,0.9rem)] font-semibold text-white">
                                Запомнить меня
                            </span>
                        </label>

                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-[clamp(0.75rem,1.8vw,0.85rem)] font-semibold text-white/95 underline decoration-white/50 underline-offset-4 hover:text-white"
                        >
                            Забыли пароль?
                        </Link>
                    </div>

                    <PrimaryButton
                        class="traklo-footer-submit justify-center bg-white text-slate-900 hover:bg-blue-50"
                        :class="{ 'opacity-50': form.processing }"
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
.traklo-bar-input {
    display: block;
    box-sizing: border-box;
    width: 100%;
    height: 100%;
    margin: 0;
    border: 0;
    border-radius: 9999px;
    background: #ffffff;
    padding: 0 0.9rem;
    font-size: clamp(0.85rem, 2.2vw, 1.05rem);
    line-height: 1.2;
    color: #0f172a;
    outline: none;
    caret-color: #1d4ed8;
    box-shadow: 0 1px 2px rgb(15 23 42 / 0.12);
}

.traklo-bar-input::placeholder {
    color: #94a3b8;
    line-height: inherit;
}

.traklo-bar-input:focus {
    box-shadow:
        0 0 0 2px rgb(255 255 255 / 0.55),
        0 0 0 4px rgb(37 99 235 / 0.45);
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

.traklo-footer-form {
    display: grid;
    height: 100%;
    grid-template-columns: 1fr auto;
    align-items: end;
    column-gap: 0.75rem;
}

.traklo-footer-links {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding-bottom: 0.15rem;
}

.traklo-footer-submit {
    min-width: 6rem;
    align-self: end;
}
</style>
