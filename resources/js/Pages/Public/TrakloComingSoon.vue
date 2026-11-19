<script setup>
import { Head, usePage } from '@inertiajs/vue3';

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
});

const page = usePage();
const waitlistMailto = 'mailto:hello@traklo.pro';

const t = (key, fallback = '') => {
    const value = props.texts[key] ?? page.props.publicSite?.texts?.[key];

    return typeof value === 'string' && value.trim() !== '' ? value : fallback;
};
</script>

<template>
    <div class="traklo-coming-soon flex min-h-dvh flex-col bg-[#070B14] text-slate-100">
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

        <header class="border-b border-white/5">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <div class="inline-flex items-center gap-2 font-semibold text-white">
                    <img
                        src="/downloads/traklo-icon.png"
                        alt=""
                        class="h-9 w-9 rounded-xl"
                    >
                    <span class="traklo-display">{{ t('brand', 'Traklo Pro') }}</span>
                </div>
                <a
                    v-if="canLogin !== false"
                    :href="crmLoginUrl"
                    class="rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-white/5 hover:text-white"
                >
                    {{ t('nav_login', 'Вход') }}
                </a>
            </div>
        </header>

        <main class="relative flex flex-1 items-center">
            <div class="pointer-events-none absolute inset-0 overflow-hidden">
                <div class="absolute -left-24 top-0 h-[22rem] w-[22rem] rounded-full bg-blue-600/20 blur-3xl" />
                <div class="absolute right-0 top-24 h-[18rem] w-[18rem] rounded-full bg-cyan-500/10 blur-3xl" />
            </div>

            <div class="relative mx-auto max-w-3xl space-y-6 px-4 py-16 sm:px-6">
                <h1 class="traklo-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                    {{ t('coming_soon_banner', 'Мы работаем над созданием Traklo Pro') }}
                </h1>
                <p class="max-w-xl text-lg leading-8 text-slate-400">
                    {{ t('coming_soon_text') }}
                </p>
                <div class="flex flex-wrap gap-3 pt-2">
                    <a
                        :href="waitlistMailto"
                        class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-900/40 hover:bg-blue-500"
                    >
                        {{ t('coming_soon_cta', 'Написать нам') }}
                    </a>
                    <a
                        v-if="canLogin !== false"
                        :href="crmLoginUrl"
                        class="rounded-xl border border-white/10 bg-white/5 px-5 py-3 text-sm font-medium text-white hover:bg-white/10"
                    >
                        {{ t('nav_login', 'Вход') }}
                    </a>
                </div>
                <p class="text-sm text-slate-500">
                    hello@traklo.pro
                </p>
            </div>
        </main>
    </div>
</template>

<style scoped>
.traklo-coming-soon {
    font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
}

.traklo-display {
    font-family: 'Instrument Sans', 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
}
</style>
