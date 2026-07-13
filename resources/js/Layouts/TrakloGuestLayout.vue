<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const showcaseHomeUrl = computed(() => page.props.showcase_home_url ?? '/');
const tenantName = computed(() => page.props.tenant?.name ?? null);
</script>

<template>
    <div class="min-h-dvh bg-[#0B1220] text-slate-100">
        <div class="mx-auto flex min-h-dvh w-full max-w-4xl flex-col px-4 py-5 sm:px-6">
            <header class="mb-4 flex items-center justify-between gap-4">
                <a
                    :href="showcaseHomeUrl"
                    class="group inline-flex items-center gap-2 rounded-lg text-sm font-medium text-slate-300 transition hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                >
                    <span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-lg bg-blue-600/20 ring-1 ring-blue-500/30">
                        <img src="/assets/favicon/favicon-96x96.png" alt="" class="h-7 w-7 object-contain" width="28" height="28">
                    </span>
                    <span>Traklo Pro</span>
                </a>
                <span v-if="tenantName" class="truncate text-xs text-slate-500">
                    {{ tenantName }}
                </span>
            </header>

            <main class="flex flex-1 flex-col items-center justify-center">
                <slot />
            </main>
        </div>
    </div>
</template>
