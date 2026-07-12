<script setup>
/**
 * Sticky scroll chapter: text scenes stack while the product shot stays pinned
 * and swaps (placeholder for future large real screenshots).
 * ponytail: CSS mocks — swap ShowcaseFeatureShot for <img> when shots exist.
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
        default: 'Прокручивайте сцены ниже — кадр справа меняется вместе с текстом.',
    },
});

const activeIndex = ref(0);
const sceneRefs = ref([]);
let sceneObserver = null;

const setSceneRef = (el, index) => {
    if (el) {
        sceneRefs.value[index] = el;
    }
};

const activeScene = computed(() => props.scenes[activeIndex.value] ?? props.scenes[0] ?? null);

const shotTilt = computed(() => (activeIndex.value % 2 === 0 ? 'right' : 'left'));

const shotShiftClass = computed(() => (
    activeIndex.value % 2 === 0
        ? 'translate-x-0 lg:translate-x-3'
        : 'translate-x-0 lg:-translate-x-3'
));

onMounted(() => {
    const nodes = sceneRefs.value.filter(Boolean);
    if (nodes.length === 0) {
        return;
    }

    sceneObserver = new IntersectionObserver(
        (entries) => {
            const visible = entries
                .filter((entry) => entry.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio);

            const top = visible[0];
            if (!top?.target) {
                return;
            }

            const index = Number(top.target.dataset.sceneIndex);
            if (!Number.isNaN(index) && activeIndex.value !== index) {
                activeIndex.value = index;
            }
        },
        {
            root: null,
            rootMargin: '-35% 0px -40% 0px',
            threshold: [0.15, 0.35, 0.55],
        },
    );

    nodes.forEach((node) => sceneObserver.observe(node));
});

onUnmounted(() => {
    sceneObserver?.disconnect();
    sceneObserver = null;
});
</script>

<template>
    <div class="showcase-sticky">
        <div class="mb-12 max-w-2xl lg:mb-16">
            <p
                v-if="eyebrow"
                class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-300/80"
            >
                {{ eyebrow }}
            </p>
            <h2
                class="traklo-display text-3xl font-semibold text-white sm:text-4xl"
                :class="eyebrow ? 'mt-3' : ''"
            >
                {{ title }}
            </h2>
            <p v-if="subtitle" class="mt-3 text-lg text-slate-400">
                {{ subtitle }}
            </p>
            <p class="mt-3 text-sm text-slate-500">
                {{ hint }}
            </p>
        </div>

        <div class="grid gap-10 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] lg:gap-14">
            <div class="space-y-6">
                <article
                    v-for="(scene, index) in scenes"
                    :key="scene.key"
                    :ref="(el) => setSceneRef(el, index)"
                    :data-scene-index="index"
                    class="showcase-sticky__scene rounded-2xl border border-white/5 bg-white/[0.02] px-5 py-10 sm:px-6 sm:py-12 lg:min-h-[70vh] lg:py-16"
                    :class="activeIndex === index ? 'border-blue-400/30 bg-blue-500/[0.06]' : ''"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-300/80">
                        {{ String(index + 1).padStart(2, '0') }} / {{ String(scenes.length).padStart(2, '0') }}
                    </p>
                    <h3 class="traklo-display mt-4 text-2xl font-semibold text-white sm:text-3xl">
                        {{ scene.title }}
                    </h3>
                    <p class="mt-4 max-w-xl text-base leading-7 text-slate-400 sm:text-lg sm:leading-8">
                        {{ scene.text }}
                    </p>

                    <!-- Mobile: shot under each scene -->
                    <div class="mt-8 lg:hidden">
                        <ShowcaseFeatureShot
                            :variant="scene.key"
                            :label="scene.label"
                            tilt="right"
                        />
                    </div>
                </article>
            </div>

            <div class="relative hidden lg:block">
                <div class="sticky top-28">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <p class="truncate text-sm text-slate-400">
                            {{ activeScene?.title }}
                        </p>
                        <div class="flex gap-1.5" aria-hidden="true">
                            <span
                                v-for="(scene, index) in scenes"
                                :key="scene.key"
                                class="h-1.5 w-1.5 rounded-full transition"
                                :class="activeIndex === index ? 'bg-blue-400' : 'bg-white/20'"
                            />
                        </div>
                    </div>

                    <div
                        class="showcase-sticky__stage transition duration-500 ease-out"
                        :class="shotShiftClass"
                    >
                        <div class="showcase-sticky__frame overflow-hidden rounded-2xl border border-white/10 bg-[#0b1220] p-2 shadow-2xl shadow-black/40">
                            <ShowcaseFeatureShot
                                v-if="activeScene"
                                :variant="activeScene.key"
                                :label="activeScene.label"
                                :tilt="shotTilt"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.traklo-display {
    font-family: 'Instrument Sans', 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
}

.showcase-sticky__frame :deep(.showcase-shot) {
    padding-top: 0.5rem;
    padding-bottom: 0.75rem;
}

.showcase-sticky__frame :deep(.showcase-shot__screen) {
    min-height: 18rem;
}

@media (prefers-reduced-motion: reduce) {
    .showcase-sticky__stage {
        transition: none;
    }
}
</style>
