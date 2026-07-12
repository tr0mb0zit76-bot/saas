<script setup>
import ShowcaseFeatureShot from '@/Components/Public/ShowcaseFeatureShot.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

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
    planMatrix: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const t = (key, fallback = '') => {
    const value = props.texts[key] ?? page.props.publicSite?.texts?.[key];

    return typeof value === 'string' && value.trim() !== '' ? value : fallback;
};

const featureFallbackTitles = {
    leads: 'Лиды и воронка',
    orders: 'Мастер заказа',
    payments: 'График оплат',
    print: 'Печать документов',
    rbac: 'Роли и области видимости',
    documents: 'Реестр документов',
    scripts: 'Скрипты продаж',
    salesbook: 'Книга продаж',
    howmuchfits: 'Сколько влезет',
    payroll: 'Начисление зарплат',
    mobile: 'Мобильное приложение',
    ai: 'Текстовый помощник',
    accounting: 'Управленческий учёт',
    budgeting: 'Бюджетирование',
    companyplanning: 'Планирование компании',
    aianalytics: 'Аналитика ИИ',
    loadboard: 'Биржа и доска грузов',
    fleet: 'Свой автопарк',
    disposition: 'Диспозиция',
    integrations: 'Интеграции',
    customdomain: 'Свой адрес кабинета',
};

const mapFeatures = (keys) => keys.map((key) => ({
    key,
    title: t(`feature_${key}_title`, featureFallbackTitles[key] ?? key),
    text: t(`feature_${key}_text`),
    label: t(`feature_${key}_label`, `кабинет · ${key}`),
}));

const featureSections = computed(() => [
    {
        id: 'features',
        eyebrow: '',
        title: t('bento_title'),
        subtitle: t('bento_subtitle'),
        tone: 'base',
        features: mapFeatures(['leads', 'orders', 'payments', 'print', 'rbac']),
    },
    {
        id: 'features-pro',
        eyebrow: t('plan_pro', 'Про'),
        title: t('pro_title', 'На тарифе Про'),
        subtitle: t('pro_subtitle'),
        tone: 'pro',
        features: mapFeatures([
            'documents',
            'scripts',
            'salesbook',
            'howmuchfits',
            'payroll',
            'mobile',
            'ai',
        ]),
    },
    {
        id: 'features-enterprise',
        eyebrow: t('plan_enterprise', 'Корпоративный'),
        title: t('enterprise_title', 'На тарифе Корпоративный'),
        subtitle: t('enterprise_subtitle'),
        tone: 'enterprise',
        features: mapFeatures([
            'accounting',
            'budgeting',
            'companyplanning',
            'aianalytics',
            'loadboard',
            'fleet',
            'disposition',
            'integrations',
            'customdomain',
        ]),
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
        {
            key: 'start',
            label: t('plan_start', 'Старт'),
            users: t('plan_start_users'),
            limits: { users: 5, orders_per_month: 200, storage_mb: 2048 },
            featured: false,
        },
        {
            key: 'pro',
            label: t('plan_pro', 'Про'),
            users: t('plan_pro_users'),
            limits: { users: 25, orders_per_month: 2000, storage_mb: 20480 },
            featured: true,
        },
        {
            key: 'enterprise',
            label: t('plan_enterprise', 'Корпоративный'),
            users: t('plan_enterprise_users'),
            limits: { users: null, orders_per_month: null, storage_mb: null },
            featured: false,
        },
    ];
});

const plansWithHighlights = computed(() => displayPlans.value.map((plan) => ({
    ...plan,
    label: t(`plan_${plan.key}`, plan.label),
    users: t(`plan_${plan.key}_users`, plan.users),
})));

const comparisonPlans = computed(() => plansWithHighlights.value);

const comparisonRows = computed(() => (Array.isArray(props.planMatrix) ? props.planMatrix : []));

const formatLimit = (value) => (value == null ? 'без лимита' : String(value));

const formatStorage = (mb) => {
    if (mb == null) {
        return 'без лимита';
    }

    return mb >= 1024 ? `${Math.round(mb / 1024)} ГБ` : `${mb} МБ`;
};

const chapters = computed(() => [
    { id: 'hero', label: t('nav_chapter_hero', 'Введение') },
    { id: 'features', label: t('nav_chapter_base', 'База') },
    { id: 'features-pro', label: t('nav_chapter_pro', 'Про') },
    { id: 'features-enterprise', label: t('nav_chapter_enterprise', 'Корпоративный') },
    { id: 'pricing', label: t('nav_chapter_pricing', 'Тарифы') },
    { id: 'connect', label: t('nav_chapter_connect', 'Подключение') },
]);

const activeChapter = ref('hero');
let chapterObserver = null;

const goToChapter = (id) => {
    const el = document.getElementById(id);
    if (!el) {
        return;
    }

    activeChapter.value = id;
    el.scrollIntoView({
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
        block: 'start',
    });
};

onMounted(() => {
    const nodes = chapters.value
        .map((chapter) => document.getElementById(chapter.id))
        .filter(Boolean);

    if (nodes.length === 0) {
        return;
    }

    chapterObserver = new IntersectionObserver(
        (entries) => {
            const visible = entries
                .filter((entry) => entry.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio);

            if (visible[0]?.target?.id) {
                activeChapter.value = visible[0].target.id;
            }
        },
        {
            root: null,
            rootMargin: '-28% 0px -52% 0px',
            threshold: [0.08, 0.2, 0.4],
        },
    );

    nodes.forEach((node) => chapterObserver.observe(node));
});

onUnmounted(() => {
    chapterObserver?.disconnect();
    chapterObserver = null;
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

        <nav
            class="traklo-chapters pointer-events-none fixed left-3 top-1/2 z-30 hidden -translate-y-1/2 xl:block 2xl:left-5"
            aria-label="Разделы страницы"
        >
            <ul class="pointer-events-auto flex flex-col gap-1.5 border-l border-white/10 pl-3">
                <li v-for="chapter in chapters" :key="chapter.id">
                    <button
                        type="button"
                        class="traklo-chapter group relative block max-w-[9.5rem] py-1 text-left text-[11px] font-medium uppercase tracking-[0.14em] transition duration-300"
                        :class="activeChapter === chapter.id
                            ? 'text-white/90'
                            : 'text-white/20 hover:text-white/55'"
                        :aria-current="activeChapter === chapter.id ? 'true' : undefined"
                        @click="goToChapter(chapter.id)"
                    >
                        <span
                            class="absolute -left-[13px] top-1/2 h-1.5 w-1.5 -translate-y-1/2 rounded-full transition duration-300"
                            :class="activeChapter === chapter.id
                                ? 'bg-blue-400 shadow-[0_0_0_3px_rgba(59,130,246,0.25)]'
                                : 'bg-white/20 group-hover:bg-white/40'"
                            aria-hidden="true"
                        />
                        {{ chapter.label }}
                    </button>
                </li>
            </ul>
        </nav>

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
            <section id="hero" class="relative scroll-mt-24 overflow-hidden">
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

            <!-- Features: base / Pro / Enterprise -->
            <section
                v-for="section in featureSections"
                :id="section.id"
                :key="section.id"
                class="relative scroll-mt-24 border-t border-white/5"
                :class="{
                    'bg-[#0a1220]/40': section.tone === 'pro',
                    'bg-[#0b1020]/70': section.tone === 'enterprise',
                }"
            >
                <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-24">
                    <div class="mb-14 max-w-2xl">
                        <p
                            v-if="section.eyebrow"
                            class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-300/80"
                        >
                            {{ section.eyebrow }}
                        </p>
                        <h2
                            class="traklo-display text-3xl font-semibold text-white sm:text-4xl"
                            :class="section.eyebrow ? 'mt-3' : ''"
                        >
                            {{ section.title }}
                        </h2>
                        <p class="mt-3 text-lg text-slate-400">
                            {{ section.subtitle }}
                        </p>
                    </div>

                    <div class="space-y-16 lg:space-y-24">
                        <article
                            v-for="(feature, index) in section.features"
                            :key="feature.key"
                            class="grid items-center gap-8 lg:grid-cols-2 lg:gap-12"
                        >
                            <div
                                class="space-y-4"
                                :class="index % 2 === 1 ? 'lg:order-2' : ''"
                            >
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-300/80">
                                    {{ String(index + 1).padStart(2, '0') }}
                                </p>
                                <h3 class="traklo-display text-2xl font-semibold text-white sm:text-3xl">
                                    {{ feature.title }}
                                </h3>
                                <p class="max-w-xl text-base leading-7 text-slate-400">
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

            <section id="pricing" class="scroll-mt-24 border-y border-white/5 bg-[#0a1220]/80">
                <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-20">
                    <div class="mb-10 max-w-2xl">
                        <h2 class="traklo-display text-3xl font-semibold text-white">{{ t('pricing_title') }}</h2>
                        <p class="mt-3 text-slate-400">{{ t('pricing_subtitle') }}</p>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-white/10 bg-white/[0.03]">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-white/10 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 font-medium">{{ t('pricing_col_module', 'Модуль') }}</th>
                                    <th class="px-4 py-3 font-medium">{{ t('pricing_col_group', 'Группа') }}</th>
                                    <th
                                        v-for="plan in comparisonPlans"
                                        :key="plan.key"
                                        class="px-4 py-3 text-center font-medium"
                                        :class="plan.featured ? 'text-blue-300' : 'text-slate-400'"
                                    >
                                        {{ plan.label }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in comparisonRows"
                                    :key="row.key"
                                    class="border-b border-white/5"
                                >
                                    <td class="px-4 py-2.5 text-slate-200">{{ row.label }}</td>
                                    <td class="px-4 py-2.5 text-slate-500">{{ row.group_label }}</td>
                                    <td
                                        v-for="plan in comparisonPlans"
                                        :key="plan.key"
                                        class="px-4 py-2.5 text-center"
                                    >
                                        <span
                                            v-if="row.plans?.[plan.key]"
                                            class="font-semibold text-emerald-400"
                                        >✓</span>
                                        <span v-else class="text-slate-600">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 grid gap-4 lg:grid-cols-3">
                        <article
                            v-for="plan in comparisonPlans"
                            :key="plan.key"
                            class="rounded-2xl border p-5"
                            :class="plan.featured ? 'border-blue-500/50 bg-blue-500/10' : 'border-white/10 bg-white/[0.03]'"
                        >
                            <h3 class="traklo-display text-lg font-semibold text-white">{{ plan.label }}</h3>
                            <dl class="mt-3 space-y-1.5 text-sm text-slate-400">
                                <div class="flex justify-between gap-3">
                                    <dt>{{ t('pricing_limit_users', 'Пользователи') }}</dt>
                                    <dd class="text-slate-200">{{ formatLimit(plan.limits?.users) }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt>{{ t('pricing_limit_orders', 'Заказы / мес') }}</dt>
                                    <dd class="text-slate-200">{{ formatLimit(plan.limits?.orders_per_month) }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt>{{ t('pricing_limit_storage', 'Хранилище') }}</dt>
                                    <dd class="text-slate-200">{{ formatStorage(plan.limits?.storage_mb) }}</dd>
                                </div>
                            </dl>
                            <a
                                href="mailto:hello@traklo.pro"
                                class="mt-5 inline-flex rounded-lg px-4 py-2 text-sm font-medium"
                                :class="plan.featured ? 'bg-blue-600 text-white hover:bg-blue-500' : 'border border-white/10 text-white hover:bg-white/5'"
                            >
                                {{ t('plan_cta', 'Обсудить') }}
                            </a>
                        </article>
                    </div>
                </div>
            </section>

            <section id="connect" class="mx-auto max-w-6xl scroll-mt-24 px-4 py-16 sm:px-6 lg:py-20">
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

.traklo-chapter {
    font-family: 'Instrument Sans', 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
}

@media (prefers-reduced-motion: reduce) {
    .traklo-chapter {
        transition: none;
    }
}
</style>
