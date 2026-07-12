<script setup>
/**
 * Horizontal story chapter driven by vertical scroll.
 * Tall outer track → sticky viewport → panels slide left/right (100vw each).
 * Soft dissolve past the Traklo brand line (~3cm); shots keep an angled view
 * that straightens on hover.
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
/** Shot index under the pointer — straighten angle on hover. */
const hoveredIndex = ref(null);
let frame = 0;

const sceneCount = computed(() => Math.max(props.scenes.length, 1));

const activeIndex = computed(() => {
    const max = sceneCount.value - 1;
    if (max <= 0) {
        return 0;
    }

    return Math.min(max, Math.max(0, Math.round(progress.value * max)));
});

/** Continuous scene position for angle interpolation. */
const scenePos = computed(() => progress.value * Math.max(sceneCount.value - 1, 0));

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

    // Dissolve past the brand line over ~3cm (plus a short soft lead-in).
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

const shotShellStyle = (index) => {
    const d = index - scenePos.value;
    const hovered = hoveredIndex.value === index;

    // Hover: ease toward face-on (same idea as ShowcaseFeatureShot hover).
    if (hovered) {
        return {
            transform: 'rotateY(-3.5deg) rotateX(2deg) rotateZ(0deg) scale(1.01) translateY(-4px)',
            opacity: '1',
        };
    }

    // Always a clear angled glance; exiting left turns further away.
    const rotY = Math.max(-28, Math.min(8, -16 + d * 10));
    const rotX = 7 + Math.min(4, Math.abs(d) * 1.4);
    const rotZ = 1.4 - Math.min(1.6, Math.abs(d) * 0.55) * Math.sign(d || -1);
    const scale = 1 - Math.min(0.08, Math.abs(d) * 0.04);
    const opacity = 1 - Math.min(0.28, Math.abs(d) * 0.14);

    return {
        transform: `rotateY(${rotY.toFixed(2)}deg) rotateX(${rotX.toFixed(2)}deg) rotateZ(${rotZ.toFixed(2)}deg) scale(${scale.toFixed(3)})`,
        opacity: opacity.toFixed(3),
    };
};

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
                        v-for="(scene, index) in scenes"
                        :key="scene.key"
                        class="showcase-rail__panel relative flex h-full w-screen shrink-0 flex-col"
                    >
                        <div
                            class="flex min-h-0 w-full flex-1 flex-col pr-4 sm:pr-6"
                            :style="columnStyle"
                        >
                            <div class="flex min-h-0 w-full max-w-6xl flex-1 flex-col">
                                <div class="showcase-rail__perspective min-h-0 flex-[1.35]">
                                    <div
                                        class="showcase-rail__shot relative h-full overflow-hidden rounded-2xl border border-white/10 bg-[#0b1220] shadow-[0_28px_60px_-28px_rgba(0,0,0,0.75)] will-change-transform"
                                        :class="{ 'showcase-rail__shot--hovered': hoveredIndex === index }"
                                        :style="shotShellStyle(index)"
                                        @mouseenter="hoveredIndex = index"
                                        @mouseleave="hoveredIndex = null"
                                    >
                                        <ShowcaseFeatureShot
                                            :variant="scene.key"
                                            :label="scene.label"
                                            fill
                                            tilt="right"
                                        />
                                    </div>
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

.showcase-rail__perspective {
    perspective: 1200px;
    perspective-origin: 58% 42%;
    transform-style: preserve-3d;
}

.showcase-rail__track,
.showcase-rail__panel,
.showcase-rail__panel > div,
.showcase-rail__panel > div > div {
    transform-style: preserve-3d;
}

.showcase-rail__shot {
    transform-style: preserve-3d;
    transform-origin: 70% 55%;
    transition:
        transform 0.5s cubic-bezier(0.22, 1, 0.36, 1),
        opacity 0.25s ease,
        box-shadow 0.5s cubic-bezier(0.22, 1, 0.36, 1);
    box-shadow:
        0 28px 60px -28px rgba(0, 0, 0, 0.75),
        -18px 12px 40px -20px rgba(15, 23, 42, 0.55);
}

.showcase-rail__shot--hovered {
    box-shadow:
        0 34px 70px -24px rgba(0, 0, 0, 0.8),
        0 0 0 1px rgba(255, 255, 255, 0.06);
}

.showcase-rail__shot :deep(.showcase-shot) {
    height: 100%;
}

.showcase-rail__shot :deep(.showcase-shot__frame) {
    height: 100%;
    border-radius: 0;
    border: 0;
    box-shadow: none;
    transform: none !important;
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

@media (prefers-reduced-motion: reduce) {
    .showcase-rail__track,
    .showcase-rail__shot {
        transition: none;
    }

    .showcase-rail__perspective {
        perspective: none;
    }

    .showcase-rail__shot {
        transform: none !important;
        opacity: 1 !important;
    }
}
</style>
