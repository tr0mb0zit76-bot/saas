<script setup>
import ShowcaseFeatureShot from '@/Components/Public/ShowcaseFeatureShot.vue';
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
    {
        key: 'leads',
        title: t('feature_leads_title', 'Лиды и воронка'),
        text: t('feature_leads_text'),
        label: 'crm · leads',
    },
    {
        key: 'orders',
        title: t('feature_orders_title', 'Мастер заказа'),
        text: t('feature_orders_text'),
        label: 'crm · order wizard',
    },
    {
        key: 'payments',
        title: t('feature_payments_title', 'График оплат'),
        text: t('feature_payments_text'),
        label: 'crm · payments',
    },
    {
        key: 'print',
        title: t('feature_print_title', 'DOCX и PDF'),
        text: t('feature_print_text'),
        label: 'crm · print',
    },
    {
        key: 'rbac',
        title: t('feature_rbac_title', 'RBAC и scopes'),
        text: t('feature_rbac_text'),
        label: 'crm · access',
    },
    {
        key: 'ai',
        title: t('feature_ai_title', 'AI Command Bar'),
        text: t('feature_ai_text'),
        label: 'crm · ai',
    },
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
    <div class="traklo-landing min-h-dvh bg-[#070B14] text-slate-100">
        <Head :title="t('brand', 'Traklo Pro')">
            <link
                rel="preconnect"
                href="https://fonts.googleapis.com"
            >
            <link
                rel="preconnect"
                href="https://fonts.gstatic.com"
                crossorigin=""
            >
            <link
                href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
                rel="stylesheet"
            >
        </Head>

        <header class="sticky top-0 z-20 border-b border-white/5 bg-[#070B14]/80 backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <a href="#" class="inline-flex items-center gap-2 font-semibold text-white">
                    <img src="/downloads/traklo-icon.png" alt="" class="h-9 w-9 rounded-xl" />
                    <span class="traklo-display">{{ t('brand', 'Traklo Pro') }}</span>
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
            <!-- Hero: brand + one CTA group + product visual -->
            <section class="relative overflow-hidden">
                <div class="pointer-events-none absolute inset-0">
                    <div class="absolute -left-24 top-0 h-[28rem] w-[28rem] rounded-full bg-blue-600/20 blur-3xl" />
                    <div class="absolute right-0 top-20 h-[22rem] w-[22rem] rounded-full bg-cyan-500/10 blur-3xl" />
                    <div
                        class="absolute inset-0 opacity-[0.07]"
                        style="background-image: linear-gradient(rgb(148 163 184) 1px, transparent 1px), linear-gradient(90deg, rgb(148 163 184) 1px, transparent 1px); background-size: 48px 48px;"
                    />
                </div>

                <div class="relative mx-auto grid max-w-6xl gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:py-24">
                    <div class="space-y-6">
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-300/90">
                            {{ t('hero_eyebrow') }}
                        </p>
                        <h1 class="traklo-display text-4xl font-semibold tracking-tight text-white sm:text-5xl lg:text-[3.25rem] lg:leading-[1.1]">
                            {{ t('hero_title') }}
                        </h1>
                        <p class="max-w-xl text-lg leading-8 text-slate-400">
                            {{ t('hero_subtitle') }}
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <a
                                href="#pricing"
                                class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-900/40 hover:bg-blue-500"
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

                    <div class="relative">
                        <ShowcaseFeatureShot
                            variant="orders"
                            :label="t('brand', 'Traklo Pro')"
                            tilt="right"
                        />
                    </div>
                </div>
            </section>

            <!-- Features: SaaS product-shot rows -->
            <section id="features" class="relative border-t border-white/5">
                <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-24">
                    <div class="mb-14 max-w-2xl">
                        <h2 class="traklo-display text-3xl font-semibold text-white sm:text-4xl">
                            {{ t('bento_title') }}
                        </h2>
                        <p class="mt-3 text-lg text-slate-400">
                            {{ t('bento_subtitle') }}
                        </p>
                    </div>

                    <div class="space-y-16 lg:space-y-24">
                        <article
                            v-for="(feature, index) in features"
                            :key="feature.key"
                            class="grid items-center gap-8 lg:grid-cols-2 lg:gap-12"
                        >
                            <div
                                class="space-y-4"
                                :class="index % 2 === 1 ? 'lg:order-2' : ''"
                            >
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-300/80">
                                    0{{ index + 1 }}
                                </p>
                                <h3 class="traklo-display text-2xl font-semibold text-white sm:text-3xl">
                                    {{ feature.title }}
                                </h3>
                                <p class="max-w-md text-base leading-7 text-slate-400">
                                    {{ feature.text }}
                                </p>
                            </div>

                            <div :class="index % 2 === 1 ? 'lg:order-1' : ''">
                                <ShowcaseFeatureShot
                                    :variant="feature.key"
                                    :label="feature.label"
                                    :tilt="index % 2 === 1 ? 'left' : 'right'"
                                />
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section id="pricing" class="border-y border-white/5 bg-[#0a1220]/80">
                <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-20">
                    <div class="mb-10 max-w-2xl">
                        <h2 class="traklo-display text-3xl font-semibold text-white">{{ t('pricing_title') }}</h2>
                        <p class="mt-3 text-slate-400">{{ t('pricing_subtitle') }}</p>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-3">
                        <article
                            v-for="plan in displayPlans"
                            :key="plan.key"
                            class="rounded-2xl border p-6"
                            :class="plan.featured ? 'border-blue-500/50 bg-blue-500/10' : 'border-white/10 bg-white/[0.03]'"
                        >
                            <h3 class="traklo-display text-xl font-semibold text-white">{{ plan.label }}</h3>
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
                <h2 class="traklo-display text-3xl font-semibold text-white">{{ t('steps_title') }}</h2>
                <ol class="mt-8 grid gap-6 lg:grid-cols-3">
                    <li
                        v-for="(step, index) in steps"
                        :key="step.title"
                        class="relative border-l border-blue-500/30 pl-5"
                    >
                        <div class="mb-3 text-sm font-semibold text-blue-300">
                            Шаг {{ index + 1 }}
                        </div>
                        <h3 class="traklo-display font-semibold text-white">{{ step.title }}</h3>
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

<style scoped>
.traklo-landing {
    font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
}

.traklo-display {
    font-family: 'Instrument Sans', 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
}
</style>
