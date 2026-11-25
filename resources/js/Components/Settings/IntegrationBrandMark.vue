<template>
    <img
        :src="src"
        :alt="title"
        :title="title"
        class="h-9 w-9 shrink-0 rounded-lg object-cover shadow-sm ring-1 ring-black/5 dark:ring-white/10"
        width="36"
        height="36"
        loading="lazy"
        decoding="async"
    >
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    adapterKey: { type: String, required: true },
    label: { type: String, default: '' },
});

/** Источник: https://trace-logos.ru/logos/bank/?s=bank */
const ICONS = {
    sber: '/assets/integrations/sber.svg?v=trace1',
    t_bank: '/assets/integrations/t-bank.svg?v=trace1',
    tochka: '/assets/integrations/tochka.svg?v=trace1',
    alfa: '/assets/integrations/alfa.svg?v=trace1',
    vtb_bank: '/assets/integrations/vtb.svg?v=trace1',
    one_c_bp: '/assets/integrations/1c.svg?v=trace1',
};

const src = computed(() => ICONS[props.adapterKey] ?? '/assets/integrations/generic.svg?v=trace1');
const title = computed(() => props.label || props.adapterKey);
</script>
