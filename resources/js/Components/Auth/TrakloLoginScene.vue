<script setup>
/**
 * Login scene uses the real traklo-icon.png.
 * White bars on the icon = measured hit-areas for email/password.
 * Truck sprite is cropped from the same icon and drives the painted route.
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
    }, 2000);
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

        <div class="traklo-icon relative mx-auto aspect-square w-full max-w-[20rem] sm:max-w-[22rem]">
            <img
                src="/downloads/traklo-icon.png"
                alt=""
                class="pointer-events-none block h-full w-full select-none object-contain"
                draggable="false"
            >

            <!-- Cover painted truck while the sprite is driving -->
            <div
                v-show="driving && !prefersReducedMotion && !instant"
                class="traklo-truck-cover pointer-events-none absolute"
                aria-hidden="true"
            />

            <!-- Truck cropped from the same icon — only while driving -->
            <img
                v-show="driving && !prefersReducedMotion && !instant"
                src="/downloads/traklo-truck-sprite.png"
                alt=""
                class="traklo-truck-sprite traklo-truck-sprite--drive pointer-events-none absolute"
                draggable="false"
            >

            <!--
              Bar hit-areas measured from traklo-icon.png (1024²):
              email    y 61.91–64.06%  x 36.23–70.02%
              password y 67.77–69.92%  x 36.13–55.47%
            -->
            <div
                class="traklo-bar traklo-bar--email absolute"
                :class="sceneReady || instant ? 'traklo-bar--live' : 'pointer-events-none opacity-0'"
            >
                <slot name="email" />
            </div>
            <div
                class="traklo-bar traklo-bar--password absolute"
                :class="sceneReady || instant ? 'traklo-bar--live' : 'pointer-events-none opacity-0'"
            >
                <slot name="password" />
            </div>
        </div>

        <div
            class="traklo-footer mx-auto mt-5 w-full max-w-[20rem] sm:max-w-[22rem]"
            :class="sceneReady || instant ? 'opacity-100' : 'pointer-events-none opacity-0'"
        >
            <slot name="footer" />
        </div>
    </div>
</template>

<style scoped>
/* Painted truck bbox ~33–62% x, 33–52% y — cover with bubble blue while driving */
.traklo-truck-cover {
    left: 33%;
    top: 33%;
    width: 30%;
    height: 20%;
    border-radius: 1rem;
    background: #17b4f6;
    filter: blur(2px);
}

.traklo-truck-sprite {
    width: 29%;
    height: auto;
    left: 33.2%;
    top: 33.2%;
    filter: drop-shadow(0 4px 8px rgb(11 58 140 / 0.25));
}

.traklo-truck-sprite--drive {
    opacity: 0;
    animation: traklo-truck-drive 1.6s cubic-bezier(0.22, 1, 0.36, 1) 0.25s forwards;
}

/* Exact bar slots from PNG measurement */
.traklo-bar {
    z-index: 2;
    transition: opacity 0.35s ease;
}

.traklo-bar--email {
    top: 61.6%;
    left: 36.2%;
    width: 33.9%;
    height: 3.6%;
}

.traklo-bar--password {
    top: 67.5%;
    left: 36.1%;
    width: 19.4%;
    height: 3.6%;
}

.traklo-bar--live {
    opacity: 1;
    pointer-events: auto;
}

.traklo-footer {
    transition: opacity 0.35s ease 0.08s;
}

/*
  Route approx from icon: left pin → dip → right pin.
  Truck parks at center dip (same place as painted truck).
*/
@keyframes traklo-truck-drive {
    0% {
        opacity: 1;
        left: 14%;
        top: 28%;
        transform: rotate(-8deg);
    }

    55% {
        opacity: 1;
        left: 33%;
        top: 40%;
        transform: rotate(4deg);
    }

    100% {
        opacity: 1;
        left: 33.2%;
        top: 33.2%;
        transform: rotate(0deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .traklo-truck-cover,
    .traklo-truck-sprite {
        display: none !important;
    }

    .traklo-bar,
    .traklo-footer {
        opacity: 1 !important;
        pointer-events: auto !important;
    }
}
</style>
