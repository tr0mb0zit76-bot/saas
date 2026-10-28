<script setup>
/**
 * Horizontal story chapter driven by vertical scroll.
 * Tall outer track → sticky viewport → panels slide left/right (100vw each).
 * Soft dissolve past the Traklo brand line (~3cm). Shots are flat for now.
 * ponytail: CSS mocks — swap ShowcaseFeatureShot for real screenshots later.
 */
import ShowcaseFeatureShot from '@/Components/Public/ShowcaseFeatureShot.vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

/** Dissolve width past the brand cut. */
const FADE = '3cm';

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
/** Left edge of header brand — start of the dissolve. */
const cutLeft = ref(0);
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
    const shiftVw = max <= 0 ? 0 : progress.value * max * 100;

    return {
        transform: `translate3d(-${shiftVw}vw, 0, 0)`,
    };
});

const stageStyle = computed(() => {
    const cut = Math.round(cutLeft.value);
    if (cut <= 0) {
        return undefined;
    }

    const mask = [
        'linear-gradient(to right,',
        'transparent 0,',
        `transparent calc(${cut}px - ${FADE}),`,
        `rgb(0 0 0 / 0.16) calc(${cut}px - 1.4cm),`,
        `rgb(0 0 0 / 0.55) calc(${cut}px - 0.45cm),`,
        `rgb(0 0 0 / 0.82) ${cut}px,`,
        `#000 calc(${cut}px + 0.65cm),`,
        '#000 100%)',
    ].join(' ');

    return {
        maskImage: mask,
        WebkitMaskImage: mask,
    };
});

const columnStyle = computed(() => {
    if (cutLeft.value <= 0) {
        return undefined;
    }

    return {
        paddingLeft: `${Math.round(cutLeft.value)}px`,
    };
});

const syncCut = () => {
    const brand = document.getElementById('traklo-brand');
    if (!brand) {
        cutLeft.value = 0;

        return;
    }

    cutLeft.value = Math.max(0, brand.getBoundingClientRect().left);
};

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

const onResize = () => {
    syncCut();
    syncProgress();
};

onMounted(() => {
    syncCut();
    syncProgress();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onResize, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('resize', onResize);
    if (frame) {
        window.cancelAnimationFrame(frame);
    }
    frame = 0;
});
</script>

<template>
    <div
        ref="chapterEl"
        class="showcase-rail"
        :style="{ height: `${Math.max(sceneCount, 1) * 100}vh` }"
    >
        <div class="sticky top-0 flex h-dvh flex-col overflow-hidden">
            <div
                class="w-full shrink-0 pr-4 pb-1 pt-14 sm:pr-6 lg:pt-16"
                :style="columnStyle"
            >
                <div class="max-w-6xl">
                    <p
                        v-if="eyebrow"
                        class="text-[11px] font-semibold uppercase tracking-[0.16em] text-blue-300/80"
                    >
                        {{ eyebrow }}
                    </p>
                    <div class="mt-1 flex flex-wrap items-end justify-between gap-3">
                        <div class="min-w-0 max-w-3xl">
                            <h2 class="traklo-display text-xl font-semibold text-white sm:text-2xl lg:text-[1.75rem]">
                                {{ title }}
                            </h2>
                            <p v-if="subtitle" class="mt-1 line-clamp-2 text-sm leading-6 text-slate-400">
                                {{ subtitle }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 text-xs uppercase tracking-[0.14em] text-slate-500">
                            <span>{{ String(activeIndex + 1).padStart(2, '0') }}</span>
                            <span>/</span>
                            <span>{{ String(sceneCount).padStart(2, '0') }}</span>
                        </div>
                    </div>
                    <p v-if="hint" class="mt-1 text-xs text-slate-500">
                        {{ hint }}
                    </p>
                </div>
            </div>

            <div
                class="showcase-rail__stage relative min-h-0 flex-1 overflow-hidden"
                :style="stageStyle"
            >
                <div
                    class="showcase-rail__track absolute inset-y-0 left-0 flex h-full will-change-transform"
                    :style="trackStyle"
                >
                    <article
                        v-for="scene in scenes"
                        :key="scene.key"
                        class="showcase-rail__panel relative flex h-full w-screen shrink-0 flex-col"
                    >
                        <div
                            class="flex min-h-0 w-full flex-1 flex-col pr-4 sm:pr-6"
                            :style="columnStyle"
                        >
                            <div class="flex min-h-0 w-full max-w-6xl flex-1 flex-col">
                                <div class="showcase-rail__shot relative min-h-0 flex-[1.35] overflow-hidden rounded-2xl border border-white/10 bg-[#0b1220]">
                                    <ShowcaseFeatureShot
                                        :variant="scene.key"
                                        :label="scene.label"
                                        fill
                                    />
                                </div>

                                <div class="showcase-rail__caption shrink-0 px-1 pb-1 pt-3 sm:px-2 sm:pt-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-blue-300/80">
                                        {{ scene.label }}
                                    </p>
                                    <h3 class="traklo-display mt-1 text-lg font-semibold text-white sm:text-xl lg:text-2xl">
                                        {{ scene.title }}
                                    </h3>
                                    <p class="mt-1.5 max-w-4xl text-sm leading-6 text-slate-300 sm:text-[0.95rem] sm:leading-7">
                                        {{ scene.text }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <div
                class="flex w-full shrink-0 justify-start gap-2 py-2.5 pr-4 sm:pr-6"
                :style="columnStyle"
                aria-hidden="true"
            >
                <div class="flex w-full max-w-6xl justify-center gap-2">
                    <span
                        v-for="(scene, index) in scenes"
                        :key="scene.key"
                        class="h-1 rounded-full transition-all duration-300"
                        :class="activeIndex === index ? 'w-8 bg-blue-400' : 'w-2 bg-white/20'"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.traklo-display {
    font-family: 'Instrument Sans', 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
}

.showcase-rail__shot :deep(.showcase-shot) {
    height: 100%;
}

.showcase-rail__shot :deep(.showcase-shot__frame) {
    height: 100%;
    border-radius: 0;
    border: 0;
    box-shadow: none;
}

.showcase-rail__shot :deep(.showcase-shot__screen) {
    min-height: 0;
    height: calc(100% - 2.5rem);
    display: flex;
    flex-direction: column;
    justify-content: center;
    font-size: 1.05em;
    padding: 1.1rem 1.25rem 1.25rem;
}

@media (min-width: 1024px) {
    .showcase-rail__shot :deep(.showcase-shot__screen) {
        font-size: 1.12em;
        padding: 1.35rem 1.6rem 1.5rem;
    }
}
</style>
