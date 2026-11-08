<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import {
    applyCrmAppearanceToDocument,
    CRM_COLOR_FIELDS,
    CRM_PALETTES,
    effectiveThemeColors,
    normalizeThemeColors,
    persistCrmAppearance,
    resolveCrmAppearance,
    writeLocalCrmAppearance,
} from '@/support/crmAppearance.js';
import { crmNavIconWrapClass } from '@/support/crmNavIconStyle.js';
import { Package, Target, Wallet } from 'lucide-vue-next';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);

const form = reactive({
    workspace_skin: 'classic',
    button_radius: 'sharp',
    primary_accent: 'emerald',
    tab_style: 'filled',
    nav_icon_style: 'monochrome',
    theme_colors: null,
});

const saving = ref(false);
const saveError = ref('');

const palette = computed(() => effectiveThemeColors(form));
const hasColorOverrides = computed(() => form.theme_colors != null);

function appearancePayload() {
    return {
        workspace_skin: form.workspace_skin,
        button_radius: form.button_radius,
        primary_accent: form.primary_accent,
        tab_style: form.tab_style,
        nav_icon_style: form.nav_icon_style,
        theme_colors: form.theme_colors,
    };
}

function syncFormFromUser() {
    const resolved = resolveCrmAppearance(authUser.value);
    form.workspace_skin = resolved.workspace_skin;
    form.button_radius = resolved.button_radius;
    form.primary_accent = resolved.primary_accent;
    form.tab_style = resolved.tab_style;
    form.nav_icon_style = resolved.nav_icon_style;
    form.theme_colors = resolved.theme_colors;
}

watch(() => props.show, (visible) => {
    if (visible) {
        saveError.value = '';
        syncFormFromUser();
    }
});

function preview() {
    writeLocalCrmAppearance(appearancePayload());
    applyCrmAppearanceToDocument(form);
}

function setSkin(skin) {
    form.workspace_skin = skin;
    form.theme_colors = null;
    preview();
}

function setColor(key, value) {
    const base = effectiveThemeColors(form);
    form.theme_colors = normalizeThemeColors(form.workspace_skin, { ...base, [key]: value });
    preview();
}

function resetColors() {
    form.theme_colors = null;
    preview();
}

function save() {
    if (saving.value) {
        return;
    }

    writeLocalCrmAppearance(appearancePayload());
    applyCrmAppearanceToDocument(form);

    const density = authUser.value?.ui_preferences?.ag_grid_density ?? 'normal';
    saving.value = true;
    saveError.value = '';

    persistCrmAppearance({
        ...appearancePayload(),
        ag_grid_density: density,
    }, {
        onSuccess: () => {
            saving.value = false;
            emit('close');
        },
        onError: () => {
            saving.value = false;
            saveError.value = 'Не удалось сохранить (сессия или CSRF). Обновите страницу (F5) и попробуйте снова.';
        },
    });
}

const radiusOptions = [
    { value: 'rounded', label: 'Скруглённые', hint: 'Кнопки и поля с мягкими углами' },
    { value: 'sharp', label: 'Прямые углы', hint: 'Строгий вид, как в таблицах' },
];

const accentOptions = [
    { value: 'emerald', label: 'Зелёные', sampleClass: 'border-emerald-200/90 bg-emerald-50 text-emerald-900 dark:border-emerald-800/70 dark:bg-emerald-950/50 dark:text-emerald-50' },
    { value: 'sky', label: 'Голубые', sampleClass: 'border-sky-200/90 bg-sky-50 text-sky-900 dark:border-sky-800/70 dark:bg-sky-950/50 dark:text-sky-50' },
];

const tabOptions = [
    { value: 'filled', label: 'Заливка' },
    { value: 'underline', label: 'Подчёркивание' },
];

const workspaceSkinOptions = [
    { value: 'classic', label: 'Классический', hint: 'Zinc-оболочка, как в таблицах.' },
    { value: 'sky', label: 'Sky', hint: 'Мягкие карточки и голубой акцент.' },
    { value: 'traklo', label: 'Traklo', hint: 'Палитра витрины Traklo Pro.' },
    { value: 'emerald', label: 'Изумруд', hint: 'Свежий зелёный акцент.' },
    { value: 'violet', label: 'Фиолет', hint: 'Спокойный фиолетовый тон.' },
    { value: 'amber', label: 'Янтарь', hint: 'Тёплый янтарный акцент.' },
    { value: 'rose', label: 'Роза', hint: 'Яркий розово-красный акцент.' },
    { value: 'teal', label: 'Бирюза', hint: 'Бирюзовый, ближе к морскому.' },
    { value: 'indigo', label: 'Индиго', hint: 'Глубокий индиго для кнопок и ссылок.' },
];

const navIconStyleOptions = [
    {
        value: 'monochrome',
        label: 'Монохромные',
        hint: 'Цвет иконки совпадает с текстом меню.',
    },
    {
        value: 'semantic',
        label: 'Цвет по разделу',
        hint: 'Лиды — зелёные, заказы — голубые, финансы — жёлтые и т.д.',
    },
    {
        value: 'tinted',
        label: 'С подложкой',
        hint: 'Цветная плашка за иконкой — удобнее в свёрнутом меню.',
    },
];

const selectedSkin = computed(() => (
    workspaceSkinOptions.find((option) => option.value === form.workspace_skin)
    ?? workspaceSkinOptions[1]
));
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <section class="bg-white dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Внешний вид</h2>
            </div>

            <div class="max-h-[min(70dvh,36rem)] space-y-6 overflow-y-auto px-5 py-5">
                <div class="space-y-2">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Тема</div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        Пресет и свои цвета — личная настройка, только для вас.
                    </p>
                    <div class="flex flex-wrap gap-2" role="group" aria-label="Готовые темы">
                        <button
                            v-for="option in workspaceSkinOptions"
                            :key="option.value"
                            type="button"
                            :aria-label="option.label"
                            :aria-pressed="form.workspace_skin === option.value"
                            :title="option.label"
                            class="h-8 w-8 rounded-full border border-zinc-300 transition dark:border-zinc-600"
                            :class="form.workspace_skin === option.value
                                ? 'ring-2 ring-zinc-900 ring-offset-2 ring-offset-white dark:ring-zinc-100 dark:ring-offset-zinc-900'
                                : 'hover:opacity-90'"
                            :style="{
                                background: `linear-gradient(135deg, ${CRM_PALETTES[option.value].surface_muted} 58%, ${CRM_PALETTES[option.value].accent} 58%)`,
                            }"
                            @click="setSkin(option.value)"
                        />
                    </div>
                    <p class="text-sm text-zinc-700 dark:text-zinc-300">
                        <span class="font-medium">{{ selectedSkin.label }}</span>
                        <span class="text-zinc-500 dark:text-zinc-400"> — {{ selectedSkin.hint }}</span>
                    </p>
                </div>

                <div class="space-y-2">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Палитра</div>
                    <ul class="space-y-1.5">
                        <li
                            v-for="field in CRM_COLOR_FIELDS"
                            :key="`${form.workspace_skin}-${field.key}-${palette[field.key]}`"
                            class="flex items-center justify-between gap-2 text-xs"
                        >
                            <label :for="`crm-color-${field.key}`" class="text-zinc-500 dark:text-zinc-400">
                                {{ field.label }}
                            </label>
                            <input
                                :id="`crm-color-${field.key}`"
                                type="color"
                                :value="palette[field.key]"
                                class="h-7 w-10 cursor-pointer rounded-lg border border-zinc-200 bg-transparent dark:border-zinc-700"
                                @input="setColor(field.key, $event.target.value)"
                            >
                        </li>
                    </ul>
                    <button
                        v-if="hasColorOverrides"
                        type="button"
                        class="text-xs text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                        @click="resetColors"
                    >
                        Вернуть цвета темы
                    </button>
                </div>

                <div class="space-y-2">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Углы элементов</div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="option in radiusOptions"
                            :key="option.value"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-left text-sm transition"
                            :class="form.button_radius === option.value
                                ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-800'
                                : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800'"
                            @click="form.button_radius = option.value; preview()"
                        >
                            <div class="font-medium">{{ option.label }}</div>
                            <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ option.hint }}</div>
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Цвет кнопок «Сохранить» и «Добавить»</div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="option in accentOptions"
                            :key="option.value"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-left transition"
                            :class="form.primary_accent === option.value
                                ? 'border-zinc-900 ring-1 ring-zinc-900 dark:border-zinc-100 dark:ring-zinc-100'
                                : 'border-zinc-200 dark:border-zinc-700'"
                            @click="form.primary_accent = option.value; preview()"
                        >
                            <span
                                class="inline-flex items-center gap-2 border px-3 py-1.5 text-sm font-medium"
                                :class="[option.sampleClass, form.button_radius === 'rounded' ? 'rounded-lg' : 'rounded-none']"
                            >
                                Пример
                            </span>
                            <div class="mt-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ option.label }}</div>
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Иконки меню</div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        Помогает быстрее узнавать разделы, особенно когда боковое меню свёрнуто.
                    </p>
                    <div class="grid gap-2">
                        <button
                            v-for="option in navIconStyleOptions"
                            :key="option.value"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-left text-sm transition"
                            :class="form.nav_icon_style === option.value
                                ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-800'
                                : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800'"
                            @click="form.nav_icon_style = option.value; preview()"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="inline-flex shrink-0 items-center justify-center"
                                    :class="[
                                        crmNavIconWrapClass('leads'),
                                        form.nav_icon_style === 'tinted' ? '' : 'h-7 w-7',
                                    ]"
                                >
                                    <Target class="h-4 w-4" />
                                </span>
                                <span
                                    class="inline-flex shrink-0 items-center justify-center"
                                    :class="[
                                        crmNavIconWrapClass('orders'),
                                        form.nav_icon_style === 'tinted' ? '' : 'h-7 w-7',
                                    ]"
                                >
                                    <Package class="h-4 w-4" />
                                </span>
                                <span
                                    class="inline-flex shrink-0 items-center justify-center"
                                    :class="[
                                        crmNavIconWrapClass('finance'),
                                        form.nav_icon_style === 'tinted' ? '' : 'h-7 w-7',
                                    ]"
                                >
                                    <Wallet class="h-4 w-4" />
                                </span>
                            </div>
                            <div class="mt-2 font-medium">{{ option.label }}</div>
                            <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ option.hint }}</div>
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Вкладки в карточках</div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="option in tabOptions"
                            :key="option.value"
                            type="button"
                            class="rounded-xl border px-4 py-2 text-sm transition"
                            :class="form.tab_style === option.value
                                ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900'
                                : 'border-zinc-200 text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                            @click="form.tab_style = option.value; preview()"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1 border border-zinc-200 bg-zinc-50/80 p-2 dark:border-zinc-700 dark:bg-zinc-900/50">
                        <span
                            class="crm-tab-btn crm-tab-btn--active-filled"
                            :data-preview-tab="form.tab_style"
                        >Основное</span>
                        <span class="crm-tab-btn crm-tab-btn--inactive">Маршрут</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-col items-end gap-2 border-t border-zinc-200 px-5 py-4 dark:border-zinc-800 sm:flex-row sm:items-center sm:justify-between">
                <p v-if="saveError" class="text-sm text-rose-600 dark:text-rose-400">
                    {{ saveError }}
                </p>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="crm-btn-neutral"
                        @click="emit('close')"
                    >
                        Отмена
                    </button>
                    <button
                        type="button"
                        class="crm-btn-create"
                        :disabled="saving"
                        @click="save"
                    >
                        {{ saving ? 'Сохранение…' : 'Сохранить' }}
                    </button>
                </div>
            </div>
        </section>
    </Modal>
</template>
