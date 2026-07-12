<script setup>
/**
 * Static traklo-icon login stage (no animation for now).
 * Overlays: title, email/password on white bars, footer controls inside the bubble.
 */
defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="traklo-login-scene mx-auto w-full max-w-3xl">
        <div class="traklo-icon relative mx-auto aspect-square w-full max-w-[min(100%,34rem)] sm:max-w-[38rem]">
            <img
                src="/downloads/traklo-icon.png"
                alt=""
                class="pointer-events-none block h-full w-full select-none object-contain"
                draggable="false"
            >

            <!-- Title inside bubble (upper zone) -->
            <div
                v-if="title || subtitle"
                class="traklo-caption absolute left-[18%] right-[18%] top-[9%] text-center"
            >
                <h1 v-if="title" class="text-[clamp(0.95rem,2.6vw,1.25rem)] font-semibold leading-tight tracking-tight text-white drop-shadow">
                    {{ title }}
                </h1>
                <p v-if="subtitle" class="mt-1 text-[clamp(0.7rem,1.8vw,0.875rem)] leading-snug text-white/90">
                    {{ subtitle }}
                </p>
            </div>

            <!--
              Bars measured on 1024² icon; height increased for readable text
              (taller than painted bars — inputs have solid white fill).
            -->
            <div class="traklo-bar traklo-bar--email absolute">
                <slot name="email" />
            </div>
            <div class="traklo-bar traklo-bar--password absolute">
                <slot name="password" />
            </div>

            <!-- Remember / submit inside lower bubble -->
            <div class="traklo-controls absolute left-[18%] right-[14%] top-[74%]">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.traklo-bar {
    z-index: 2;
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

.traklo-caption,
.traklo-controls {
    z-index: 2;
}
</style>
