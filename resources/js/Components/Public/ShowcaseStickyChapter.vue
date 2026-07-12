<script setup>
/**
 * Horizontal story chapter driven by vertical scroll.
 * Tall outer track → sticky viewport → panels slide left/right.
 * Large shot on top; caption full-width underneath (and over a gradient layer).
 * ponytail: CSS mocks — swap ShowcaseFeatureShot for real screenshots later.
 */
import ShowcaseFeatureShot from '@/Components/Public/ShowcaseFeatureShot.vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    eyebrow: { type: String, default: '' },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    scenes: {
        type: Array,
        default: () => [],
    },
    hint: {
        type: String,
        default: '',
    },
});

const chapterEl = ref(null);
const progress = ref(0);
let frame = 0;

const sceneCount = computed(() => Math.max(props.scenes.length, 1));

const activeIndex = computed(() => {
    const max = sceneCount.value - 1;
    if (max <= 0) {
        return 0;
    }

    return Math.min(max, Math.max(0, Math.round(progress.value * max)));
});

const trackStyle = computed(() => {
    const max = sceneCount.value - 1;
    const shift = max <= 0 ? 0 : progress.value * max * 100;

    return {
        transform: `translate3d(-${shift}%, 0, 0)`,
        width: `${sceneCount.value * 100}%`,
    };
});

const syncProgress = () => {
    const el = chapterEl.value;
    if (!el) {
        return;
    }

    const rect = el.getBoundingClientRect();
    const travel = el.offsetHeight - window.innerHeight;
    if (travel <= 0) {
        progress.value = 0;

        return;
    }

    // 0 while sticky engages, 1 when leaving the chapter
    const raw = (-rect.top) / travel;
    progress.value = Math.min(1, Math.max(0, raw));
};

const onScroll = () => {
    if (frame) {
        return;
    }

    frame = window.requestAnimationFrame(() => {
        frame = 0;
        syncProgress();
    });
};

onMounted(() => {
    syncProgress();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('resize', onScroll);
    if (frame) {
        window.cancelAnimationFrame(frame);
        frame = 0;
    }
});
</script>

<template>
    <div
        ref="chapterEl"
        class="showcase-rail"
        :style="{ height: `${Math.max(sceneCount, 1) * 100}vh` }"
    >
        <div class="sticky top-0 flex h-dvh flex-col overflow-hidden">
            <div class="mx-auto w-full max-w-6xl shrink-0 px-4 pb-3 pt-20 sm:px-6 lg:pt-24">
                <p
                    v-if="eyebrow"
                    class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-300/80"
                >
                    {{ eyebrow }}
                </p>
                <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                    <div class="min-w-0 max-w-3xl">
                        <h2 class="traklo-display text-3xl font-semibold text-white sm:text-4xl">
                            {{ title }}
                        </h2>
                        <p v-if="subtitle" class="mt-2 text-base text-slate-400 sm:text-lg">
                            {{ subtitle }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 text-xs uppercase tracking-[0.14em] text-slate-500">
                        <span>{{ String(activeIndex + 1).padStart(2, '0') }}</span>
                        <span>/</span>
                        <span>{{ String(sceneCount).padStart(2, '0') }}</span>
                    </div>
                </div>
                <p v-if="hint" class="mt-2 text-sm text-slate-500">
                    {{ hint }}
                </p>
            </div>

            <div class="relative min-h-0 flex-1">
                <div
                    class="showcase-rail__track absolute inset-0 flex h-full will-change-transform"
                    :style="trackStyle"
                >
                    <article
                        v-for="(scene, index) in scenes"
                        :key="scene.key"
                        class="showcase-rail__panel relative flex h-full shrink-0 flex-col"
                        :style="{ width: `${100 / sceneCount}%` }"
                    >
                        <div class="relative mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col px-4 sm:px-6">
                            <!-- Large shot layer -->
                            <div class="showcase-rail__shot relative min-h-0 flex-1 overflow-hidden rounded-2xl border border-white/10 bg-[#0b1220]">
                                <div class="absolute inset-0 flex items-center justify-center p-3 sm:p-5 lg:p-6">
                                    <div class="showcase-rail__shot-inner h-full w-full max-w-5xl">
                                        <ShowcaseFeatureShot
                                            :variant="scene.key"
                                            :label="scene.label"
                                            :tilt="index % 2 === 0 ? 'right' : 'left'"
                                        />
                                    </div>
                                </div>

                                <!-- Caption sits over lower shot layer -->
                                <div class="pointer-events-none absolute inset-x-0 bottom-0 z-10 bg-gradient-to-t from-[#070B14] via-[#070B14]/90 to-transparent px-5 pb-5 pt-16 sm:px-8 sm:pb-7">
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-300/80">
                                        {{ String(index + 1).padStart(2, '0') }} · {{ scene.label }}
                                    </p>
                                    <h3 class="traklo-display mt-2 text-2xl font-semibold text-white sm:text-3xl">
                                        {{ scene.title }}
                                    </h3>
                                    <p class="mt-2 max-w-none text-sm leading-6 text-slate-300 sm:text-base sm:leading-7">
                                        {{ scene.text }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <div class="mx-auto flex w-full max-w-6xl shrink-0 justify-center gap-2 px-4 py-4 sm:px-6" aria-hidden="true">
                <span
                    v-for="(scene, index) in scenes"
                    :key="scene.key"
                    class="h-1 rounded-full transition-all duration-300"
                    :class="activeIndex === index ? 'w-8 bg-blue-400' : 'w-2 bg-white/20'"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.traklo-display {
    font-family: 'Instrument Sans', 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
}

.showcase-rail__track {
    transition: none;
}

.showcase-rail__shot-inner {
    transform: scale(1.08);
    transform-origin: center center;
}

.showcase-rail__shot-inner :deep(.showcase-shot) {
    height: 100%;
    padding: 0;
}

.showcase-rail__shot-inner :deep(.showcase-shot__frame) {
    height: 100%;
    min-height: 100%;
}

.showcase-rail__shot-inner :deep(.showcase-shot__screen) {
    min-height: clamp(16rem, 52vh, 28rem);
}

@media (prefers-reduced-motion: reduce) {
    .showcase-rail__track {
        transition: none;
    }
}
</style>
