<template>
    <div class="flex min-h-screen flex-col bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
        <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3">
                <div class="flex items-center gap-6">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-sky-600">Traklo Pro</div>
                        <div class="text-sm font-semibold">Platform Admin</div>
                    </div>
                    <nav class="hidden items-center gap-1 sm:flex">
                        <Link
                            v-for="item in navItems"
                            :key="item.key"
                            :href="item.href"
                            class="rounded-lg px-3 py-2 text-sm transition"
                            :class="activeKey === item.key
                                ? 'bg-sky-100 font-medium text-sky-800 dark:bg-sky-950 dark:text-sky-200'
                                : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <Link :href="route('dashboard')" class="text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">
                        ← CRM
                    </Link>
                    <span class="text-zinc-400">{{ authUser?.name }}</span>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6">
            <slot />
        </main>
    </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    activeKey: { type: String, default: 'dashboard' },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);

const navItems = [
    { key: 'dashboard', label: 'Обзор', href: route('platform.dashboard') },
    { key: 'tenants', label: 'Арендаторы', href: route('platform.tenants.index') },
    { key: 'plans', label: 'Тарифы и модули', href: route('platform.plans.index') },
];
</script>
