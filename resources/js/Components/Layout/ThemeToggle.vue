<template>
    <button
        type="button"
        @click="toggleTheme"
        class="flex h-10 w-10 items-center justify-center rounded-xl border border-zinc-200 hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
        :title="isDark ? 'Светлая тема' : 'Тёмная тема'"
    >
        <Sun v-if="isDark" class="h-5 w-5" />
        <Moon v-else class="h-5 w-5" />
    </button>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Moon, Sun } from 'lucide-vue-next';
import {
    applyCrmAppearanceToDocument,
    readLocalCrmAppearance,
    resolveCrmAppearance,
} from '@/support/crmAppearance.js';

const page = usePage();
const isDark = ref(false);

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
});

function toggleTheme() {
    const html = document.documentElement;

    if (html.classList.contains('dark')) {
        html.classList.remove('dark');
        localStorage.setItem('theme', 'light');
        isDark.value = false;
    } else {
        html.classList.add('dark');
        localStorage.setItem('theme', 'dark');
        isDark.value = true;
    }

    // Re-paint --crm-* tokens for the new color-scheme (light/dark palettes).
    applyCrmAppearanceToDocument(resolveCrmAppearance(page.props.auth?.user ?? {
        ui_preferences: readLocalCrmAppearance(),
    }));
}
</script>
