<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    ready: {
        type: Boolean,
        default: false,
    },
    /** Skip animation (validation errors, reduced motion). */
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
    }, 1450);
});
</script>

<template>
    <div
        class="traklo-login-scene relative mx-auto aspect-square w-full max-w-[min(100%,18rem)] select-none sm:max-w-[20rem]"
        :class="{ 'traklo-login-scene--ready': sceneReady }"
    >
        <svg
            class="h-full w-full"
            viewBox="0 0 512 512"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <defs>
                <linearGradient id="traklo-bubble" x1="120" y1="80" x2="420" y2="420" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#3B82F6" />
                    <stop offset="1" stop-color="#2563EB" />
                </linearGradient>
                <linearGradient id="traklo-bubble-shadow" x1="140" y1="100" x2="440" y2="440" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#1D4ED8" />
                    <stop offset="1" stop-color="#1E3A8A" />
                </linearGradient>
                <filter id="traklo-soft-glow" x="-20%" y="-20%" width="140%" height="140%">
                    <feDropShadow dx="0" dy="8" stdDeviation="12" flood-color="#2563EB" flood-opacity="0.35" />
                </filter>
            </defs>

            <rect width="512" height="512" rx="108" fill="#0B1220" />

            <path
                d="M148 118C148 94.8172 166.817 76 190 76H360C383.183 76 402 94.8172 402 118V332C402 355.183 383.183 374 360 374H248L196 418V374H190C166.817 374 148 355.183 148 332V118Z"
                fill="url(#traklo-bubble-shadow)"
                transform="translate(8 10)"
            />

            <path
                d="M140 110C140 86.8172 158.817 68 182 68H352C375.183 68 394 86.8172 394 110V324C394 347.183 375.183 366 352 366H240L188 410V366H182C158.817 366 140 347.183 140 324V110Z"
                fill="url(#traklo-bubble)"
                filter="url(#traklo-soft-glow)"
            />

            <path
                id="traklo-route-line"
                class="traklo-route-line"
                d="M 118 408 C 158 368, 198 328, 238 298 S 298 262, 332 248"
                stroke="#93C5FD"
                stroke-width="14"
                stroke-linecap="round"
                fill="none"
            />

            <g transform="translate(118 408)">
                <circle cx="0" cy="0" r="20" fill="#FFFFFF" opacity="0.25" />
                <path d="M0 -26C10.493 -26 19 -17.493 19 -8C19 2.5 0 24 0 24C0 24 -19 2.5 -19 -8C-19 -17.493 -10.493 -26 0 -26Z" fill="#FFFFFF" />
                <circle cx="0" cy="-8" r="7" fill="#2563EB" />
            </g>

            <g transform="translate(332 248)">
                <circle cx="0" cy="0" r="16" fill="#FFFFFF" opacity="0.2" />
                <path d="M0 -22C8.837 -22 16 -14.837 16 -6C16 2.15 0 20 0 20C0 20 -16 2.15 -16 -6C-16 -14.837 -8.837 -22 0 -22Z" fill="#FFFFFF" />
                <circle cx="0" cy="-6" r="5" fill="#2563EB" />
            </g>

            <path
                id="traklo-truck-route"
                d="M 118 408 C 158 368, 198 328, 238 298 S 298 262, 332 248"
                fill="none"
                stroke="transparent"
            />

            <g class="traklo-truck-parked">
                <rect
                    class="traklo-cargo-box"
                    x="304"
                    y="228"
                    width="72"
                    height="44"
                    rx="6"
                    fill="#FFFFFF"
                />
                <path d="M276 272H384V254C384 246 378 240 370 240H348L338 228H304C292 228 284 236 284 246V272Z" fill="#FFFFFF" />
                <rect x="290" y="246" width="22" height="16" rx="3" fill="#BFDBFE" />
                <circle cx="304" cy="272" r="10" fill="#1E3A8A" />
                <circle cx="304" cy="272" r="5" fill="#93C5FD" />
                <circle cx="364" cy="272" r="10" fill="#1E3A8A" />
                <circle cx="364" cy="272" r="5" fill="#93C5FD" />
            </g>

            <rect x="176" y="318" width="148" height="12" rx="6" fill="#FFFFFF" opacity="0.85" />
            <rect x="176" y="340" width="96" height="12" rx="6" fill="#FFFFFF" opacity="0.65" />
        </svg>

        <div
            v-show="driving && !prefersReducedMotion && !instant"
            class="traklo-truck-rider pointer-events-none absolute left-0 top-0"
            aria-hidden="true"
        >
            <svg viewBox="0 0 120 70" class="h-[3.25rem] w-[5.5rem] -translate-x-1/2 -translate-y-1/2">
                <rect x="44" y="8" width="58" height="36" rx="5" fill="#FFFFFF" />
                <path d="M6 58H114V40C114 32 108 26 100 26H78L66 12H24C12 12 4 20 4 30V58Z" fill="#FFFFFF" />
                <rect x="10" y="28" width="20" height="14" rx="2" fill="#BFDBFE" />
                <circle cx="26" cy="58" r="9" fill="#1E3A8A" />
                <circle cx="26" cy="58" r="4.5" fill="#93C5FD" />
                <circle cx="92" cy="58" r="9" fill="#1E3A8A" />
                <circle cx="92" cy="58" r="4.5" fill="#93C5FD" />
            </svg>
        </div>

        <!-- Mini fields on cargo (visual); full form sits below in layout -->
        <div
            class="traklo-cargo-fields pointer-events-none absolute inset-0"
            :class="{ 'traklo-cargo-fields--active': sceneReady }"
            aria-hidden="true"
        >
            <div class="traklo-cargo-fields__stack">
                <span class="traklo-cargo-field" />
                <span class="traklo-cargo-field traklo-cargo-field--short" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.traklo-route-line {
    stroke-dasharray: 340;
    stroke-dashoffset: 340;
    animation: traklo-draw-route 0.85s ease-out forwards;
}

.traklo-truck-parked {
    opacity: 0;
    transition: opacity 0.35s ease;
}

.traklo-login-scene--ready .traklo-truck-parked {
    opacity: 1;
}

.traklo-login-scene--ready .traklo-cargo-box {
    animation: traklo-cargo-glow 1.6s ease-in-out infinite;
}

.traklo-truck-rider {
    left: 12%;
    top: 68%;
    animation: traklo-truck-drive 1.15s cubic-bezier(0.22, 1, 0.36, 1) 0.25s forwards;
    opacity: 0;
}

.traklo-cargo-fields {
    opacity: 0;
}

.traklo-cargo-fields__stack {
    position: absolute;
    left: 59.5%;
    top: 44.5%;
    width: 14%;
    transform: translate(-50%, -50%);
    display: flex;
    flex-direction: column;
    gap: 0.28rem;
}

.traklo-cargo-field {
    display: block;
    height: 0.42rem;
    border-radius: 9999px;
    background: rgb(191 219 254 / 0.95);
    box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.35);
}

.traklo-cargo-field--short {
    width: 72%;
}

.traklo-cargo-fields--active {
    animation: traklo-cargo-fields-in 0.4s ease forwards;
}

@keyframes traklo-draw-route {
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes traklo-truck-drive {
    0% {
        opacity: 1;
        left: 12%;
        top: 68%;
        transform: rotate(-24deg);
    }

    100% {
        opacity: 1;
        left: 54%;
        top: 38%;
        transform: rotate(6deg);
    }
}

@keyframes traklo-cargo-fields-in {
    from {
        opacity: 0;
        transform: scale(0.92);
    }

    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes traklo-cargo-glow {
    0%,
    100% {
        filter: drop-shadow(0 0 0 rgb(147 197 253 / 0));
    }

    50% {
        filter: drop-shadow(0 0 10px rgb(147 197 253 / 0.65));
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
    .traklo-cargo-fields {
        opacity: 1;
        animation: none;
    }
}
</style>
