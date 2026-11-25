<template>
    <nav
        class="flex flex-wrap items-center gap-1.5 rounded-xl border border-zinc-200 bg-zinc-50/80 p-2 dark:border-zinc-700 dark:bg-zinc-900/40"
        aria-label="Оглавление книги планирования"
    >
        <span class="px-2 text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Книга</span>
        <a
            v-for="sheet in sheets"
            :key="sheet.key"
            :href="sheet.href"
            :title="sheet.hint"
            class="rounded-lg px-2.5 py-1 text-xs font-medium transition"
            :class="sheet.active
                ? 'bg-white text-zinc-900 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-50 dark:ring-zinc-600'
                : 'text-zinc-600 hover:bg-white/80 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
        >
            {{ sheet.label }}
        </a>
    </nav>
</template>

<script setup>
defineProps({
    sheets: {
        type: Array,
        default: () => [],
    },
});
</script>
