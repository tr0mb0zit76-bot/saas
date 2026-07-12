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
const iconFailed = ref(false);

const onIconError = () => {
    iconFailed.value = true;
};

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
    }, 1800);
});
</script>

<template>
    <div
        class="traklo-login-scene mx-auto w-full max-w-lg"
        :class="{ 'traklo-login-scene--ready': sceneReady }"
    >
        <div class="traklo-login-scene__bubble relative overflow-hidden rounded-[2rem] px-5 pb-8 pt-6 shadow-2xl shadow-blue-950/40 sm:px-8 sm:pb-10 sm:pt-8">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-blue-500 via-blue-600 to-blue-800" aria-hidden="true" />
            <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-white/10 blur-2xl" aria-hidden="true" />
            <div class="pointer-events-none absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-blue-900/40 blur-2xl" aria-hidden="true" />

            <div class="relative z-10">
                <div v-if="title || subtitle" class="mb-5 text-center sm:mb-6">
                    <h1 v-if="title" class="text-lg font-semibold tracking-tight text-white sm:text-xl">
                        {{ title }}
                    </h1>
                    <p v-if="subtitle" class="mt-1 text-sm leading-6 text-blue-100/90">
                        {{ subtitle }}
                    </p>
                </div>

                <!-- Map area: route, pins, truck -->
                <div class="traklo-login-scene__map relative mx-auto aspect-[5/4] w-full max-w-[22rem] sm:max-w-none">
                    <svg
                        class="absolute inset-0 h-full w-full"
                        viewBox="0 0 400 320"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                        aria-hidden="true"
                    >
                        <path
                            class="traklo-route-line"
                            d="M 56 268 C 110 228, 150 188, 198 152 S 268 118, 318 98"
                            stroke="#BFDBFE"
                            stroke-width="8"
                            stroke-linecap="round"
                            fill="none"
                        />

                        <!-- Start pin -->
                        <g transform="translate(56 268)">
                            <circle cx="0" cy="0" r="22" fill="#FFFFFF" opacity="0.22" />
                            <path d="M0 -28C11.046 -28 20 -19.046 20 -10C20 0.5 0 26 0 26C0 26 -20 0.5 -20 -10C-20 -19.046 -11.046 -28 0 -28Z" fill="#FFFFFF" />
                            <circle cx="0" cy="-10" r="7" fill="#2563EB" />
                        </g>

                        <!-- Destination pin -->
                        <g transform="translate(318 98)">
                            <circle cx="0" cy="0" r="18" fill="#FFFFFF" opacity="0.18" />
                            <path d="M0 -24C9.941 -24 18 -15.941 18 -7C18 1.85 0 22 0 22C0 22 -18 1.85 -18 -7C-18 -15.941 -9.941 -24 0 -24Z" fill="#FFFFFF" />
                            <circle cx="0" cy="-7" r="6" fill="#2563EB" />
                        </g>
                    </svg>

                    <!-- Driving truck (icon) -->
                    <div
                        v-show="driving && !prefersReducedMotion && !instant"
                        class="traklo-truck-rider pointer-events-none absolute"
                        aria-hidden="true"
                    >
                        <img
                            v-if="!iconFailed"
                            src="/downloads/traklo-icon.png"
                            alt=""
                            class="h-14 w-14 rounded-xl bg-white/95 object-contain p-1 shadow-lg shadow-blue-950/30 sm:h-16 sm:w-16"
                            @error="onIconError"
                        >
                        <svg v-else viewBox="0 0 64 64" class="h-14 w-14 rounded-xl bg-white p-2 shadow-lg sm:h-16 sm:w-16" aria-hidden="true">
                            <rect x="22" y="10" width="34" height="22" rx="4" fill="#2563EB" />
                            <path d="M6 52H58V36C58 30 54 26 48 26H34L26 16H12C6 16 2 20 2 26V52Z" fill="#1D4ED8" />
                            <circle cx="16" cy="52" r="6" fill="#1E3A8A" />
                            <circle cx="48" cy="52" r="6" fill="#1E3A8A" />
                        </svg>
                    </div>

                    <!-- Parked truck at destination -->
                    <div
                        class="traklo-truck-parked pointer-events-none absolute"
                        aria-hidden="true"
                    >
                        <img
                            v-if="!iconFailed"
                            src="/downloads/traklo-icon.png"
                            alt=""
                            class="h-16 w-16 rounded-xl bg-white object-contain p-1.5 shadow-xl shadow-blue-950/35 sm:h-[4.5rem] sm:w-[4.5rem]"
                            @error="onIconError"
                        >
                        <svg v-else viewBox="0 0 64 64" class="h-16 w-16 rounded-xl bg-white p-2 shadow-xl sm:h-[4.5rem] sm:w-[4.5rem]" aria-hidden="true">
                            <rect x="22" y="10" width="34" height="22" rx="4" fill="#2563EB" />
                            <path d="M6 52H58V36C58 30 54 26 48 26H34L26 16H12C6 16 2 20 2 26V52Z" fill="#1D4ED8" />
                            <circle cx="16" cy="52" r="6" fill="#1E3A8A" />
                            <circle cx="48" cy="52" r="6" fill="#1E3A8A" />
                        </svg>
                    </div>
                </div>

                <!-- Cargo panel: real form lives here -->
                <div
                    class="traklo-cargo-panel relative z-20 -mt-2 rounded-2xl border border-white/25 bg-white p-4 shadow-xl shadow-blue-950/25 sm:p-5"
                    :class="sceneReady || instant
                        ? 'traklo-cargo-panel--visible'
                        : 'pointer-events-none opacity-0'"
                >
                    <slot />
                </div>
            </div>

            <!-- Speech bubble tail -->
            <div
                class="pointer-events-none absolute -bottom-3 left-1/2 h-8 w-8 -translate-x-1/2 rotate-45 bg-blue-700"
                aria-hidden="true"
            />
        </div>
    </div>
</template>

<style scoped>
.traklo-route-line {
    stroke-dasharray: 420;
    stroke-dashoffset: 420;
    animation: traklo-draw-route 1s ease-out forwards;
}

.traklo-truck-rider {
    left: 10%;
    top: 76%;
    animation: traklo-truck-drive 1.35s cubic-bezier(0.22, 1, 0.36, 1) 0.35s forwards;
    opacity: 0;
}

.traklo-truck-parked {
    left: 72%;
    top: 22%;
    opacity: 0;
    transform: translate(-50%, -50%) scale(0.85);
    transition: opacity 0.4s ease, transform 0.4s ease;
}

.traklo-login-scene--ready .traklo-truck-parked {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1);
}

.traklo-cargo-panel {
    transform: translateY(12px);
    transition:
        opacity 0.45s ease,
        transform 0.45s ease;
}

.traklo-cargo-panel--visible {
    opacity: 1;
    transform: translateY(0);
}

@keyframes traklo-draw-route {
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes traklo-truck-drive {
    0% {
        opacity: 1;
        left: 10%;
        top: 76%;
        transform: translate(-50%, -50%) rotate(-18deg);
    }

    100% {
        opacity: 1;
        left: 72%;
        top: 22%;
        transform: translate(-50%, -50%) rotate(8deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .traklo-route-line {
        stroke-dashoffset: 0;
        animation: none;
    }

    .traklo-truck-rider {
        display: none !important;
    }

    .traklo-truck-parked,
    .traklo-cargo-panel {
        opacity: 1 !important;
        transform: none !important;
        pointer-events: auto !important;
    }
}
</style>
