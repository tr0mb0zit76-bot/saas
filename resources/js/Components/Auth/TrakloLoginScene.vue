<script setup>
/**
 * Login scene = traklo-icon.png «разобранная» на слои:
 * bubble + route + pins + truck (белая ситуэтка как на иконке) + слоты под inputs вместо белых полос.
 */
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    ready: { type: Boolean, default: false },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    instant: { type: Boolean, default: false },
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
    }, 2100);
});
</script>

<template>
    <div
        class="traklo-login-scene mx-auto w-full max-w-md"
        :class="{ 'traklo-login-scene--ready': sceneReady }"
    >
        <div v-if="title || subtitle" class="mb-5 text-center">
            <h1 v-if="title" class="text-lg font-semibold tracking-tight text-white sm:text-xl">
                {{ title }}
            </h1>
            <p v-if="subtitle" class="mt-1 text-sm leading-6 text-slate-400">
                {{ subtitle }}
            </p>
        </div>

        <div class="traklo-icon relative mx-auto aspect-square w-full max-w-[19rem] sm:max-w-[21rem]">
            <!-- Layer: bubble shell (shape of traklo-icon) -->
            <svg
                class="absolute inset-0 h-full w-full drop-shadow-[0_18px_40px_rgba(37,99,235,0.35)]"
                viewBox="0 0 512 512"
                xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true"
            >
                <defs>
                    <linearGradient id="trakloBubbleFill" x1="120" y1="40" x2="400" y2="420" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#5BA3FF" />
                        <stop offset="0.55" stop-color="#2F7BFF" />
                        <stop offset="1" stop-color="#1D5FE0" />
                    </linearGradient>
                    <linearGradient id="trakloBubbleRim" x1="80" y1="60" x2="420" y2="420" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#93C5FD" />
                        <stop offset="1" stop-color="#1E40AF" />
                    </linearGradient>
                    <filter id="trakloInnerGlow" x="-10%" y="-10%" width="120%" height="120%">
                        <feDropShadow dx="0" dy="10" stdDeviation="14" flood-color="#0B3AA8" flood-opacity="0.35" />
                    </filter>
                </defs>

                <!-- Soft depth under bubble -->
                <path
                    d="M96 72C96 40.6 121.6 16 153 16H359C390.4 16 416 40.6 416 72V318C416 349.4 390.4 374 359 374H248L168 452V374H153C121.6 374 96 349.4 96 318V72Z"
                    fill="#123A8C"
                    transform="translate(10 14)"
                    opacity="0.55"
                />

                <!-- Bubble rim -->
                <path
                    d="M96 72C96 40.6 121.6 16 153 16H359C390.4 16 416 40.6 416 72V318C416 349.4 390.4 374 359 374H248L168 452V374H153C121.6 374 96 349.4 96 318V72Z"
                    fill="url(#trakloBubbleRim)"
                />

                <!-- Bubble face -->
                <path
                    d="M112 84C112 56.4 134.4 36 162 36H350C377.6 36 400 56.4 400 84V306C400 333.6 377.6 354 350 354H246L176 418V354H162C134.4 354 112 333.6 112 306V84Z"
                    fill="url(#trakloBubbleFill)"
                    filter="url(#trakloInnerGlow)"
                />
            </svg>

            <!-- Layer: map (route + pins + truck) — same composition as icon -->
            <svg
                class="pointer-events-none absolute inset-0 h-full w-full"
                viewBox="0 0 512 512"
                xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true"
            >
                <!-- Single route from the icon (no second road) -->
                <path
                    id="trakloRoute"
                    class="traklo-route"
                    d="M140 168 C 196 168, 214 248, 256 258 S 316 248, 372 168"
                    stroke="#BFD9FF"
                    stroke-width="10"
                    stroke-linecap="round"
                    fill="none"
                />

                <!-- Start pin -->
                <g transform="translate(140 168)">
                    <circle cx="0" cy="0" r="18" fill="#FFFFFF" opacity="0.22" />
                    <path d="M0 -30C12.15 -30 22 -20.15 22 -10C22 2 0 30 0 30C0 30 -22 2 -22 -10C-22 -20.15 -12.15 -30 0 -30Z" fill="#FFFFFF" />
                    <circle cx="0" cy="-10" r="8" fill="#2F7BFF" />
                </g>

                <!-- End pin -->
                <g transform="translate(372 168)">
                    <circle cx="0" cy="0" r="18" fill="#FFFFFF" opacity="0.22" />
                    <path d="M0 -30C12.15 -30 22 -20.15 22 -10C22 2 0 30 0 30C0 30 -22 2 -22 -10C-22 -20.15 -12.15 -30 0 -30Z" fill="#FFFFFF" />
                    <circle cx="0" cy="-10" r="8" fill="#2F7BFF" />
                </g>

                <!-- Truck silhouette matching traklo-icon (cab left, cargo right) -->
                <g
                    class="traklo-truck"
                    :class="{
                        'traklo-truck--driving': driving && !prefersReducedMotion && !instant,
                        'traklo-truck--parked': sceneReady || prefersReducedMotion || instant,
                    }"
                >
                    <!-- cargo -->
                    <rect x="-6" y="-28" width="58" height="34" rx="5" fill="#FFFFFF" />
                    <!-- cab body -->
                    <path d="M-58 18 H62 V-2 C62 -10 56 -16 48 -16 H18 L6 -28 H-42 C-54 -28 -62 -20 -62 -10 V18 Z" fill="#FFFFFF" />
                    <!-- window -->
                    <rect x="-52" y="-10" width="20" height="12" rx="2" fill="#9EC5FF" />
                    <!-- wheels -->
                    <circle cx="-34" cy="18" r="9" fill="#E8F0FE" />
                    <circle cx="-34" cy="18" r="4.5" fill="#94A3B8" />
                    <circle cx="34" cy="18" r="9" fill="#E8F0FE" />
                    <circle cx="34" cy="18" r="4.5" fill="#94A3B8" />
                </g>
            </svg>

            <!-- Layer: input slots = the two white bars from the icon -->
            <div
                class="traklo-bars absolute left-[21.5%] right-[21.5%] top-[64.5%]"
                :class="sceneReady || instant ? 'traklo-bars--ready' : 'pointer-events-none opacity-0'"
            >
                <slot name="bars" />
            </div>
        </div>

        <div
            class="traklo-footer mx-auto mt-5 w-full max-w-[19rem] sm:max-w-[21rem]"
            :class="sceneReady || instant ? 'opacity-100' : 'pointer-events-none opacity-0'"
        >
            <slot name="footer" />
        </div>
    </div>
</template>

<style scoped>
.traklo-route {
    stroke-dasharray: 340;
    stroke-dashoffset: 340;
    animation: traklo-draw-route 0.95s ease-out forwards;
}

.traklo-truck {
    transform-box: fill-box;
    transform-origin: center;
    offset-path: path('M140 168 C 196 168, 214 248, 256 258 S 316 248, 372 168');
    offset-rotate: auto 0deg;
}

.traklo-truck--driving {
    animation: traklo-truck-drive 1.55s cubic-bezier(0.22, 1, 0.36, 1) 0.35s forwards;
    opacity: 0;
}

.traklo-truck--parked {
    offset-distance: 50%;
    opacity: 1;
}

.traklo-bars {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    transition: opacity 0.4s ease;
}

.traklo-bars--ready {
    opacity: 1;
    pointer-events: auto;
}

.traklo-footer {
    transition: opacity 0.35s ease 0.1s;
}

@keyframes traklo-draw-route {
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes traklo-truck-drive {
    0% {
        opacity: 1;
        offset-distance: 0%;
    }

    100% {
        opacity: 1;
        offset-distance: 50%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .traklo-route {
        stroke-dashoffset: 0;
        animation: none;
    }

    .traklo-truck--driving {
        animation: none;
        opacity: 0;
    }

    .traklo-bars,
    .traklo-footer {
        opacity: 1 !important;
        pointer-events: auto !important;
    }
}

@supports not (offset-path: path('M0 0')) {
    .traklo-truck--driving {
        animation: traklo-truck-drive-fallback 1.55s cubic-bezier(0.22, 1, 0.36, 1) 0.35s forwards;
    }

    @keyframes traklo-truck-drive-fallback {
        0% {
            opacity: 1;
            transform: translate(140px, 168px);
        }

        100% {
            opacity: 1;
            transform: translate(256px, 248px);
        }
    }
}
</style>
