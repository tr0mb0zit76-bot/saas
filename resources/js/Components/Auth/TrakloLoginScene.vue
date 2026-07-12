<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    ready: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
    instant: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:ready']);

const prefersReducedMotion = ref(false);
const driving = ref(true);

const sceneReady = computed(() => props.ready || props.instant || !driving.value);

watch(sceneReady, (value) => {
    if (value) {
        emit('update:ready', true);
    }
}, { immediate: true });

onMounted(() => {
    if (props.instant) {
        driving.value = false;

        return;
    }

    prefersReducedMotion.value =
        typeof window !== 'undefined'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (prefersReducedMotion.value) {
        driving.value = false;

        return;
    }

    window.setTimeout(() => {
        driving.value = false;
    }, 2000);
});
</script>

<template>
    <div
        class="traklo-login-scene mx-auto w-full max-w-md"
        :class="{ 'traklo-login-scene--ready': sceneReady }"
    >
        <div v-if="title || subtitle" class="mb-4 text-center sm:mb-5">
            <h1 v-if="title" class="text-lg font-semibold tracking-tight text-white sm:text-xl">
                {{ title }}
            </h1>
            <p v-if="subtitle" class="mt-1 text-sm leading-6 text-slate-400">
                {{ subtitle }}
            </p>
        </div>

        <!-- Traklo icon = speech bubble frame; truck drives inside; inputs on white bars -->
        <div class="traklo-icon-stage relative mx-auto aspect-square w-full max-w-[20rem] sm:max-w-[22rem]">
            <img
                src="/downloads/traklo-icon.png"
                alt=""
                class="pointer-events-none block h-full w-full select-none object-contain"
            >

            <!-- Route redraw + driving truck (over map area, not over input bars) -->
            <div class="traklo-map-layer pointer-events-none absolute inset-0" aria-hidden="true">
                <svg
                    class="h-full w-full"
                    viewBox="0 0 100 100"
                    preserveAspectRatio="xMidYMid meet"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        class="traklo-route-line"
                        d="M 15 31 C 34 31, 42 52, 50 54 S 66 52, 85 31"
                        stroke="#FFFFFF"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        opacity="0.55"
                    />
                </svg>

                <div
                    v-show="driving && !prefersReducedMotion && !instant"
                    class="traklo-truck-rider absolute"
                >
                    <svg viewBox="0 0 120 64" class="h-[18%] w-auto min-w-[3.5rem] drop-shadow-md" aria-hidden="true">
                        <rect x="52" y="6" width="58" height="30" rx="4" fill="#FFFFFF" />
                        <path d="M4 58H116V40C116 32 110 26 102 26H78L66 12H22C10 12 2 20 2 30V58Z" fill="#FFFFFF" />
                        <rect x="8" y="28" width="22" height="14" rx="2" fill="#BFDBFE" opacity="0.9" />
                        <circle cx="24" cy="58" r="9" fill="#E2E8F0" />
                        <circle cx="24" cy="58" r="4.5" fill="#94A3B8" />
                        <circle cx="96" cy="58" r="9" fill="#E2E8F0" />
                        <circle cx="96" cy="58" r="4.5" fill="#94A3B8" />
                    </svg>
                </div>
            </div>

            <!-- Email + password aligned to the two white bars on the icon -->
            <div
                class="traklo-bar-fields absolute inset-x-0"
                :class="sceneReady || instant ? 'traklo-bar-fields--visible' : 'pointer-events-none'"
            >
                <slot name="bars" />
            </div>
        </div>

        <div
            class="traklo-login-footer mx-auto mt-4 w-full max-w-[20rem] sm:max-w-[22rem]"
            :class="sceneReady || instant ? 'opacity-100' : 'pointer-events-none opacity-0'"
        >
            <slot name="footer" />
        </div>
    </div>
</template>

<style scoped>
/* Map animation layer — clip to upper part so truck never covers input bars */
.traklo-map-layer {
    clip-path: inset(0 0 34% 0);
}

.traklo-route-line {
    stroke-dasharray: 120;
    stroke-dashoffset: 120;
    animation: traklo-draw-route 0.9s ease-out forwards;
}

.traklo-truck-rider {
    left: 15%;
    top: 31%;
    opacity: 0;
    animation: traklo-truck-drive 1.5s cubic-bezier(0.25, 1, 0.35, 1) 0.4s forwards;
}

/* White bars on icon: ~71% and ~81% from top */
.traklo-bar-fields {
    top: 69.5%;
    left: 11.5%;
    right: 11.5%;
    opacity: 0;
    transform: translateY(6px);
    transition:
        opacity 0.45s ease 0.1s,
        transform 0.45s ease 0.1s;
}

.traklo-bar-fields--visible {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}

.traklo-login-footer {
    transition: opacity 0.4s ease 0.15s;
}

@keyframes traklo-draw-route {
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes traklo-truck-drive {
    0% {
        opacity: 1;
        left: 15%;
        top: 31%;
        transform: translate(-50%, -50%) rotate(-6deg);
    }

    100% {
        opacity: 1;
        left: 50%;
        top: 48%;
        transform: translate(-50%, -50%) rotate(0deg);
    }
}

.traklo-login-scene--ready .traklo-truck-rider {
    display: none;
}

@media (prefers-reduced-motion: reduce) {
    .traklo-route-line {
        stroke-dashoffset: 0;
        animation: none;
    }

    .traklo-truck-rider {
        display: none !important;
    }

    .traklo-bar-fields,
    .traklo-login-footer {
        opacity: 1 !important;
        transform: none !important;
        pointer-events: auto !important;
    }
}
</style>
