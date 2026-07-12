<script setup>
/**
 * Plays pre-rendered frames (truck composited on empty icon), then settles on original PNG.
 * Frames: public/downloads/traklo-login-frames/ (see scripts/render-traklo-login-frames.php)
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    ready: { type: Boolean, default: false },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    instant: { type: Boolean, default: false },
});

const emit = defineEmits(['update:ready']);

const prefersReducedMotion = ref(false);
const playing = ref(false);
const frameIndex = ref(0);
const frameUrls = ref([]);
const durationMs = ref(1700);
const settled = ref(false);

let raf = 0;
let startedAt = 0;

const sceneReady = computed(
    () => props.ready || props.instant || settled.value || prefersReducedMotion.value,
);

const currentSrc = computed(() => {
    if (settled.value || prefersReducedMotion.value || props.instant || frameUrls.value.length === 0) {
        return '/downloads/traklo-icon.png';
    }

    return frameUrls.value[Math.min(frameIndex.value, frameUrls.value.length - 1)]
        ?? '/downloads/traklo-icon.png';
});

watch(sceneReady, (value) => {
    if (value) {
        emit('update:ready', true);
    }
}, { immediate: true });

const preload = (urls) => Promise.all(
    urls.map(
        (url) => new Promise((resolve) => {
            const img = new Image();
            img.onload = () => resolve(url);
            img.onerror = () => resolve(url);
            img.src = url;
        }),
    ),
);

const tick = (now) => {
    if (! playing.value) {
        return;
    }
    const elapsed = now - startedAt;
    const total = durationMs.value;
    const n = frameUrls.value.length;
    if (n === 0) {
        finish();

        return;
    }
    const idx = Math.min(n - 1, Math.floor((elapsed / total) * n));
    frameIndex.value = idx;
    if (elapsed >= total) {
        finish();

        return;
    }
    raf = window.requestAnimationFrame(tick);
};

const finish = () => {
    playing.value = false;
    settled.value = true;
    if (raf) {
        window.cancelAnimationFrame(raf);
        raf = 0;
    }
};

const startPlayback = async () => {
    try {
        const res = await fetch('/downloads/traklo-login-frames/manifest.json', { cache: 'no-cache' });
        if (! res.ok) {
            settled.value = true;

            return;
        }
        const manifest = await res.json();
        const files = Array.isArray(manifest.files) ? manifest.files : [];
        if (files.length === 0) {
            settled.value = true;

            return;
        }
        durationMs.value = Number(manifest.duration_ms) || 1700;
        frameUrls.value = files;
        await preload(files);
        playing.value = true;
        startedAt = performance.now();
        raf = window.requestAnimationFrame(tick);
    } catch {
        settled.value = true;
    }
};

onMounted(() => {
    prefersReducedMotion.value =
        typeof window !== 'undefined'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (props.instant || prefersReducedMotion.value) {
        settled.value = true;

        return;
    }

    startPlayback();
});

onBeforeUnmount(() => {
    if (raf) {
        window.cancelAnimationFrame(raf);
    }
});
</script>

<template>
    <div
        class="traklo-login-scene mx-auto w-full max-w-2xl"
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

        <div class="traklo-icon relative mx-auto aspect-square w-full max-w-[min(100%,28rem)] sm:max-w-[32rem]">
            <img
                :src="currentSrc"
                alt=""
                class="pointer-events-none block h-full w-full select-none object-contain"
                draggable="false"
            >

            <!--
              Bar hit-areas measured from traklo-icon.png (1024²):
              email    y 61.91–64.06%  x 36.23–70.02%
              password y 67.77–69.92%  x 36.13–55.47%
            -->
            <div
                class="traklo-bar traklo-bar--email absolute"
                :class="sceneReady ? 'traklo-bar--live' : 'pointer-events-none opacity-0'"
            >
                <slot name="email" />
            </div>
            <div
                class="traklo-bar traklo-bar--password absolute"
                :class="sceneReady ? 'traklo-bar--live' : 'pointer-events-none opacity-0'"
            >
                <slot name="password" />
            </div>
        </div>

        <div
            class="traklo-footer mx-auto mt-5 w-full max-w-[min(100%,28rem)] sm:max-w-[32rem]"
            :class="sceneReady ? 'opacity-100' : 'pointer-events-none opacity-0'"
        >
            <slot name="footer" />
        </div>
    </div>
</template>

<style scoped>
.traklo-bar {
    z-index: 2;
    transition: opacity 0.35s ease;
}

.traklo-bar--email {
    top: 61.4%;
    left: 36.2%;
    width: 33.9%;
    height: 4.2%;
}

.traklo-bar--password {
    top: 67.3%;
    left: 36.1%;
    width: 19.4%;
    height: 4.2%;
}

.traklo-bar--live {
    opacity: 1;
    pointer-events: auto;
}

.traklo-footer {
    transition: opacity 0.35s ease 0.08s;
}
</style>
