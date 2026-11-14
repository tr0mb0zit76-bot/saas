<script setup>
/**
 * Static traklo-icon login stage.
 * Soft edge blend into page bg; title above bubble; controls in lower bubble.
 */
defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="traklo-login-scene mx-auto w-full max-w-3xl">
        <!-- Title sits above the bubble so it doesn't collide with the icon rim -->
        <div
            v-if="title || subtitle"
            class="traklo-caption mx-auto mb-3 max-w-[min(100%,34rem)] text-center sm:mb-4 sm:max-w-[38rem]"
        >
            <h1 v-if="title" class="text-[clamp(1.05rem,2.8vw,1.4rem)] font-semibold leading-tight tracking-tight text-white">
                {{ title }}
            </h1>
            <p v-if="subtitle" class="mt-1.5 text-[clamp(0.8rem,2vw,0.95rem)] font-medium leading-snug text-slate-300">
                {{ subtitle }}
            </p>
        </div>

        <div class="traklo-icon relative mx-auto aspect-square w-full max-w-[min(100%,34rem)] sm:max-w-[38rem]">
            <!-- Soft glow so bubble merges with page atmosphere -->
            <div class="traklo-icon-glow pointer-events-none absolute inset-[6%] -z-0" aria-hidden="true" />

            <div class="traklo-icon-frame relative z-[1] h-full w-full">
                <img
                    src="/downloads/traklo-icon.png"
                    alt=""
                    class="traklo-icon-img pointer-events-none block h-full w-full select-none object-contain"
                    draggable="false"
                >
                <!-- Fade square PNG corners / bevel into page background -->
                <div class="traklo-icon-fade pointer-events-none absolute inset-0" aria-hidden="true" />
            </div>

            <div class="traklo-bar traklo-bar--email absolute z-[2]">
                <slot name="email" />
            </div>
            <div class="traklo-bar traklo-bar--password absolute z-[2]">
                <slot name="password" />
            </div>

            <div class="traklo-controls absolute z-[2]">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.traklo-icon-glow {
    border-radius: 50%;
    background: radial-gradient(circle, rgb(56 160 255 / 0.28) 0%, rgb(11 18 32 / 0) 68%);
    filter: blur(28px);
}

.traklo-icon-frame {
    /* Soft circular reveal — hides hard square corners of the PNG */
    -webkit-mask-image: radial-gradient(circle at 50% 46%, #000 52%, transparent 73%);
    mask-image: radial-gradient(circle at 50% 46%, #000 52%, transparent 73%);
}

.traklo-icon-fade {
    background: radial-gradient(
        circle at 50% 46%,
        transparent 48%,
        rgb(11 18 32 / 0.35) 62%,
        rgb(11 18 32 / 0.92) 78%,
        #0b1220 100%
    );
}

.traklo-bar {
    /* Opaque pill behind the input so dark-scheme autofill can't go translucent. */
    border-radius: 9999px;
    background: #ffffff;
    box-shadow: 0 1px 2px rgb(15 23 42 / 0.12);
}

.traklo-bar--email {
    top: 60.8%;
    left: 35.5%;
    width: 35%;
    height: 5.8%;
}

.traklo-bar--password {
    top: 67.2%;
    left: 35.5%;
    width: 20.5%;
    height: 5.8%;
}

/*
 * Chromium on html.dark paints -webkit-autofill as a frosted chip and ignores
 * background-color. Force light color-scheme + inset fill so saved credentials
 * stay opaque white inside the bubble bars (same idea as mobile-shell-theme).
 */
.traklo-bar :deep(input) {
    color-scheme: light;
}

.traklo-bar :deep(input:-webkit-autofill),
.traklo-bar :deep(input:-webkit-autofill:hover),
.traklo-bar :deep(input:-webkit-autofill:focus),
.traklo-bar :deep(input:-webkit-autofill:active) {
    -webkit-text-fill-color: #0f172a !important;
    caret-color: #1d4ed8;
    border-radius: 9999px;
    box-shadow: 0 0 0 100vmax #ffffff inset !important;
    transition: background-color 99999s ease-out 0s;
}

.traklo-bar :deep(input:-webkit-autofill:focus) {
    box-shadow:
        0 0 0 100vmax #ffffff inset,
        0 0 0 2px rgb(255 255 255 / 0.55),
        0 0 0 4px rgb(37 99 235 / 0.45) !important;
}

/* Lower + a bit right; room for button in bottom-right of bubble face */
.traklo-controls {
    top: 78%;
    left: 30%;
    right: 11%;
    bottom: 7%;
}
</style>
