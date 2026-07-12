<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    canLogin: Boolean,
    texts: {
        type: Object,
        default: () => ({}),
    },
    crmLoginUrl: {
        type: String,
        default: '/login',
    },
    plans: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const t = (key, fallback = '') => {
    const value = props.texts[key] ?? page.props.publicSite?.texts?.[key];

    return typeof value === 'string' && value.trim() !== '' ? value : fallback;
};

const features = computed(() => [
    { key: 'leads', title: t('feature_leads_title', 'Лиды и воронка'), text: t('feature_leads_text') },
    { key: 'orders', title: t('feature_orders_title', 'Мастер заказа'), text: t('feature_orders_text') },
    { key: 'payments', title: t('feature_payments_title', 'График оплат'), text: t('feature_payments_text') },
    { key: 'print', title: t('feature_print_title', 'DOCX и PDF'), text: t('feature_print_text') },
    { key: 'rbac', title: t('feature_rbac_title', 'RBAC и scopes'), text: t('feature_rbac_text') },
    { key: 'ai', title: t('feature_ai_title', 'AI Command Bar'), text: t('feature_ai_text') },
]);

const steps = computed(() => [
    { title: t('step1_title'), text: t('step1_text') },
    { title: t('step2_title'), text: t('step2_text') },
    { title: t('step3_title'), text: t('step3_text') },
]);

const displayPlans = computed(() => {
    if (props.plans.length > 0) {
        return props.plans;
    }

    return [
        { key: 'start', label: t('plan_start', 'Start'), users: t('plan_start_users') },
        { key: 'pro', label: t('plan_pro', 'Pro'), users: t('plan_pro_users'), featured: true },
        { key: 'enterprise', label: t('plan_enterprise', 'Enterprise'), users: t('plan_enterprise_users') },
    ];
});
</script>

<template>
    <div class="min-h-dvh bg-[#0B1220] text-slate-100">
        <Head :title="t('brand', 'Traklo Pro')" />

        <header class="sticky top-0 z-20 border-b border-white/5 bg-[#0B1220]/80 backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <a href="#" class="inline-flex items-center gap-2 font-semibold text-white">
                    <img src="/downloads/traklo-icon.png" alt="" class="h-9 w-9 rounded-xl" />
                    <span>{{ t('brand', 'Traklo Pro') }}</span>
                </a>
                <nav class="hidden items-center gap-6 text-sm text-slate-300 md:flex">
                    <a href="#features" class="hover:text-white">{{ t('nav_features', 'Возможности') }}</a>
                    <a href="#pricing" class="hover:text-white">{{ t('nav_pricing', 'Тарифы') }}</a>
                </nav>
                <div class="flex items-center gap-2">
                    <a
                        v-if="canLogin !== false"
                        :href="crmLoginUrl"
                        class="rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-white/5 hover:text-white"
                    >
                        {{ t('nav_login', 'Вход') }}
                    </a>
                    <a
                        href="#pricing"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-500"
                    >
                        {{ t('nav_trial', 'Запросить пилот') }}
                    </a>
                </div>
            </div>
        </header>

        <main>
            <section class="relative overflow-hidden border-b border-white/5">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(37,99,235,0.25),transparent_45%),radial-gradient(circle_at_80%_0%,rgba(59,130,246,0.15),transparent_35%)]" />
                <div class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:items-center lg:py-24">
                    <div class="relative space-y-6">
                        <p class="text-sm font-medium uppercase tracking-wider text-blue-300">
                            {{ t('hero_eyebrow') }}
                        </p>
                        <h1 class="text-4xl font-semibold tracking-tight text-white sm:text-5xl">
                            {{ t('hero_title') }}
                        </h1>
                        <p class="max-w-xl text-lg leading-8 text-slate-400">
                            {{ t('hero_subtitle') }}
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <a
                                href="#pricing"
                                class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-medium text-white hover:bg-blue-500"
                            >
                                {{ t('hero_cta_primary') }}
                            </a>
                            <a
                                v-if="canLogin !== false"
                                :href="crmLoginUrl"
                                class="rounded-xl border border-white/10 bg-white/5 px-5 py-3 text-sm font-medium text-white hover:bg-white/10"
                            >
                                {{ t('hero_cta_secondary') }}
                            </a>
                        </div>
                        <p class="text-sm text-slate-500">
                            {{ t('hero_note') }}
                        </p>
                    </div>

                    <div class="relative flex justify-center lg:justify-end">
                        <div class="relative w-full max-w-md">
                            <div class="absolute -inset-4 rounded-[2rem] bg-blue-500/10 blur-2xl" />
                            <img
                                src="/downloads/traklo-icon.png"
                                alt="Traklo"
                                class="relative w-full max-w-[18rem] rounded-[2rem] shadow-2xl shadow-blue-900/30"
                            />
                            <div class="absolute -bottom-4 left-4 right-4 rounded-2xl border border-white/10 bg-[#111827]/90 p-4 backdrop-blur">
                                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                                    <div class="rounded-lg bg-white/5 p-2">
                                        <div class="font-semibold text-white">Лиды</div>
                                        <div class="text-slate-500">воронка</div>
                                    </div>
                                    <div class="rounded-lg bg-white/5 p-2">
                                        <div class="font-semibold text-white">Заказы</div>
                                        <div class="text-slate-500">wizard</div>
                                    </div>
                                    <div class="rounded-lg bg-white/5 p-2">
                                        <div class="font-semibold text-white">Оплаты</div>
                                        <div class="text-slate-500">график</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="features" class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-20">
                <div class="mb-10 max-w-2xl">
                    <h2 class="text-3xl font-semibold text-white">{{ t('bento_title') }}</h2>
                    <p class="mt-3 text-slate-400">{{ t('bento_subtitle') }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="feature in features"
                        :key="feature.key"
                        class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 backdrop-blur-sm transition hover:border-blue-500/30 hover:bg-white/[0.05]"
                    >
                        <h3 class="text-lg font-medium text-white">{{ feature.title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-400">{{ feature.text }}</p>
                    </article>
                </div>
            </section>

            <section id="pricing" class="border-y border-white/5 bg-[#0f172a]/50">
                <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-20">
                    <div class="mb-10 max-w-2xl">
                        <h2 class="text-3xl font-semibold text-white">{{ t('pricing_title') }}</h2>
                        <p class="mt-3 text-slate-400">{{ t('pricing_subtitle') }}</p>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-3">
                        <article
                            v-for="plan in displayPlans"
                            :key="plan.key"
                            class="rounded-2xl border p-6"
                            :class="plan.featured ? 'border-blue-500/50 bg-blue-500/10' : 'border-white/10 bg-white/[0.03]'"
                        >
                            <h3 class="text-xl font-semibold text-white">{{ plan.label }}</h3>
                            <p class="mt-2 text-sm text-slate-400">{{ plan.users }}</p>
                            <a
                                href="mailto:hello@traklo.pro"
                                class="mt-6 inline-flex rounded-lg px-4 py-2 text-sm font-medium"
                                :class="plan.featured ? 'bg-blue-600 text-white hover:bg-blue-500' : 'border border-white/10 text-white hover:bg-white/5'"
                            >
                                {{ t('plan_cta', 'Обсудить') }}
                            </a>
                        </article>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-20">
                <h2 class="text-3xl font-semibold text-white">{{ t('steps_title') }}</h2>
                <ol class="mt-8 grid gap-4 lg:grid-cols-3">
                    <li
                        v-for="(step, index) in steps"
                        :key="step.title"
                        class="rounded-2xl border border-white/10 bg-white/[0.03] p-5"
                    >
                        <div class="mb-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-600/20 text-sm font-semibold text-blue-300">
                            {{ index + 1 }}
                        </div>
                        <h3 class="font-medium text-white">{{ step.title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-400">{{ step.text }}</p>
                    </li>
                </ol>
            </section>
        </main>

        <footer class="border-t border-white/5">
            <div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-8 text-sm text-slate-500 sm:px-6 md:flex-row md:items-center md:justify-between">
                <p>{{ t('footer_product') }}</p>
                <p>{{ t('footer_contact') }}</p>
                <p>{{ t('footer_legal') }}</p>
            </div>
        </footer>
    </div>
</template>
