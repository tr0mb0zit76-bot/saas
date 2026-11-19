import { router } from '@inertiajs/vue3';

export const CRM_APPEARANCE_CHANGED = 'crm-appearance-changed';

export const CRM_COLOR_FIELDS = [
    { key: 'accent', label: 'Акцент', css: '--crm-accent' },
    { key: 'accent_soft', label: 'Акцент, фон', css: '--crm-accent-soft' },
    { key: 'surface', label: 'Поверхность', css: '--crm-surface' },
    { key: 'surface_muted', label: 'Фон', css: '--crm-surface-muted' },
    { key: 'border', label: 'Линии', css: '--crm-border' },
    { key: 'text', label: 'Текст', css: '--crm-text' },
    { key: 'text_muted', label: 'Подписи', css: '--crm-text-muted' },
    { key: 'accent_contrast', label: 'Текст на акценте', css: '--crm-accent-contrast' },
];

export const CRM_PALETTES = {
    classic: {
        accent: '#71717a',
        accent_soft: '#f4f4f5',
        surface: '#ffffff',
        surface_muted: '#fafafa',
        border: '#e4e4e7',
        text: '#18181b',
        text_muted: '#52525b',
        accent_contrast: '#ffffff',
    },
    sky: {
        accent: '#0284c7',
        accent_soft: '#e0f2fe',
        surface: '#ffffff',
        surface_muted: '#fafafa',
        border: '#e4e4e7',
        text: '#18181b',
        text_muted: '#52525b',
        accent_contrast: '#ffffff',
    },
    traklo: {
        accent: '#2563eb',
        accent_soft: '#dbeafe',
        surface: '#ffffff',
        surface_muted: '#f8fafc',
        border: '#e2e8f0',
        text: '#0f172a',
        text_muted: '#475569',
        accent_contrast: '#ffffff',
    },
    emerald: {
        accent: '#059669',
        accent_soft: '#d1fae5',
        surface: '#ffffff',
        surface_muted: '#fafafa',
        border: '#e4e4e7',
        text: '#18181b',
        text_muted: '#52525b',
        accent_contrast: '#ffffff',
    },
    violet: {
        accent: '#7c3aed',
        accent_soft: '#ede9fe',
        surface: '#ffffff',
        surface_muted: '#fafafa',
        border: '#e4e4e7',
        text: '#18181b',
        text_muted: '#52525b',
        accent_contrast: '#ffffff',
    },
    amber: {
        accent: '#d97706',
        accent_soft: '#fef3c7',
        surface: '#ffffff',
        surface_muted: '#fafafa',
        border: '#e4e4e7',
        text: '#18181b',
        text_muted: '#52525b',
        accent_contrast: '#ffffff',
    },
    rose: {
        accent: '#e11d48',
        accent_soft: '#ffe4e6',
        surface: '#ffffff',
        surface_muted: '#fafafa',
        border: '#e4e4e7',
        text: '#18181b',
        text_muted: '#52525b',
        accent_contrast: '#ffffff',
    },
    teal: {
        accent: '#0d9488',
        accent_soft: '#ccfbf1',
        surface: '#ffffff',
        surface_muted: '#fafafa',
        border: '#e4e4e7',
        text: '#18181b',
        text_muted: '#52525b',
        accent_contrast: '#ffffff',
    },
    indigo: {
        accent: '#4f46e5',
        accent_soft: '#e0e7ff',
        surface: '#ffffff',
        surface_muted: '#fafafa',
        border: '#e4e4e7',
        text: '#18181b',
        text_muted: '#52525b',
        accent_contrast: '#ffffff',
    },
};

/** Dark counterparts — must stay in sync with html.dark rules in crm-*-theme.css */
const CRM_DARK_BASE = {
    surface: '#18181b',
    surface_muted: '#09090b',
    border: '#3f3f46',
    text: '#f4f4f5',
    text_muted: '#a1a1aa',
    accent_contrast: '#09090b',
};

export const CRM_PALETTES_DARK = {
    classic: { ...CRM_DARK_BASE, accent: '#a1a1aa', accent_soft: '#27272a' },
    sky: { ...CRM_DARK_BASE, accent: '#38bdf8', accent_soft: '#0c4a6e' },
    traklo: {
        accent: '#60a5fa',
        accent_soft: '#1e3a8a',
        surface: '#0b1220',
        surface_muted: '#070b14',
        border: '#334155',
        text: '#f1f5f9',
        text_muted: '#94a3b8',
        accent_contrast: '#070b14',
    },
    emerald: { ...CRM_DARK_BASE, accent: '#34d399', accent_soft: '#064e3b' },
    violet: { ...CRM_DARK_BASE, accent: '#a78bfa', accent_soft: '#4c1d95' },
    amber: { ...CRM_DARK_BASE, accent: '#fbbf24', accent_soft: '#78350f' },
    rose: { ...CRM_DARK_BASE, accent: '#fb7185', accent_soft: '#881337' },
    teal: { ...CRM_DARK_BASE, accent: '#2dd4bf', accent_soft: '#115e59' },
    indigo: { ...CRM_DARK_BASE, accent: '#818cf8', accent_soft: '#312e81' },
};

export const CRM_APPEARANCE_DEFAULTS = {
    button_radius: 'rounded',
    primary_accent: 'sky',
    tab_style: 'filled',
    workspace_skin: 'sky',
    nav_icon_style: 'monochrome',
    theme_colors: null,
};

const STORAGE_KEY = 'crm_appearance_v1';
const HEX = /^#[0-9a-fA-F]{6}$/;

let persistTimer = null;

/**
 * @param {string} skin
 * @param {{ dark?: boolean }} [options]
 */
export function paletteForSkin(skin, options = {}) {
    if (options.dark) {
        return CRM_PALETTES_DARK[skin] ?? CRM_PALETTES_DARK.sky;
    }

    return CRM_PALETTES[skin] ?? CRM_PALETTES.sky;
}

export function documentPrefersDarkColorScheme() {
    if (typeof document === 'undefined') {
        return false;
    }

    return document.documentElement.classList.contains('dark');
}

/**
 * @param {string} hex
 * @returns {string|null}
 */
export function hexToRgbChannels(hex) {
    if (typeof hex !== 'string' || !HEX.test(hex)) {
        return null;
    }

    const value = Number.parseInt(hex.slice(1), 16);
    const r = (value >> 16) & 255;
    const g = (value >> 8) & 255;
    const b = value & 255;

    return `${r} ${g} ${b}`;
}

/**
 * @param {string} skin
 * @param {Record<string, string>|null|undefined} value
 * @returns {Record<string, string>|null}
 */
export function normalizeThemeColors(skin, value) {
    if (!value || typeof value !== 'object') {
        return null;
    }

    const preset = paletteForSkin(skin);
    const next = { ...preset };
    let changed = false;

    for (const field of CRM_COLOR_FIELDS) {
        const item = value[field.key];
        if (typeof item === 'string' && HEX.test(item)) {
            const hex = item.toLowerCase();
            next[field.key] = hex;
            if (hex !== preset[field.key].toLowerCase()) {
                changed = true;
            }
        }
    }

    return changed ? next : null;
}

/**
 * Effective palette for pickers (preset + overrides).
 * Dark mode uses dark presets; light custom overrides are not applied on dark
 * (they would paint light text onto dark shells / white text on white cards).
 *
 * @param {{ workspace_skin?: string, theme_colors?: Record<string, string>|null }} appearance
 * @param {{ dark?: boolean }} [options]
 */
export function effectiveThemeColors(appearance, options = {}) {
    const dark = Boolean(options.dark);
    const skin = appearance?.workspace_skin ?? CRM_APPEARANCE_DEFAULTS.workspace_skin;
    const preset = paletteForSkin(skin, { dark });

    if (dark) {
        return preset;
    }

    const overrides = normalizeThemeColors(skin, appearance?.theme_colors);

    return overrides ?? preset;
}

/**
 * @param {{ ui_preferences?: Record<string, unknown> }|null|undefined} authUser
 */
export function resolveCrmAppearance(authUser) {
    const fromServer = pickAppearanceFields(authUser?.ui_preferences ?? {});
    const fromLocal = pickAppearanceFields(readLocalCrmAppearance());
    const hasAuthenticatedPreferences = authUser?.ui_preferences != null
        && typeof authUser.ui_preferences === 'object';

    const appearance = hasAuthenticatedPreferences
        ? { ...CRM_APPEARANCE_DEFAULTS, ...fromServer }
        : { ...CRM_APPEARANCE_DEFAULTS, ...fromLocal };

    const density = authUser?.ui_preferences?.ag_grid_density ?? readLocalCrmAppearance().ag_grid_density;

    if (density === 'compact' || density === 'normal' || density === 'comfortable') {
        appearance.ag_grid_density = density;
    }

    appearance.theme_colors = normalizeThemeColors(
        appearance.workspace_skin,
        appearance.theme_colors,
    );

    return appearance;
}

/**
 * @param {Record<string, unknown>|null|undefined} source
 */
function pickAppearanceFields(source) {
    if (!source || typeof source !== 'object') {
        return {};
    }

    const result = {};

    if (source.button_radius === 'sharp' || source.button_radius === 'rounded') {
        result.button_radius = source.button_radius;
    }

    if (source.primary_accent === 'emerald' || source.primary_accent === 'sky') {
        result.primary_accent = source.primary_accent;
    }

    if (source.tab_style === 'filled' || source.tab_style === 'underline') {
        result.tab_style = source.tab_style;
    }

    if (typeof source.workspace_skin === 'string' && Object.prototype.hasOwnProperty.call(CRM_PALETTES, source.workspace_skin)) {
        result.workspace_skin = source.workspace_skin;
    }

    if (source.nav_icon_style === 'monochrome' || source.nav_icon_style === 'semantic' || source.nav_icon_style === 'tinted') {
        result.nav_icon_style = source.nav_icon_style;
    }

    if (Object.prototype.hasOwnProperty.call(source, 'theme_colors')) {
        result.theme_colors = source.theme_colors === null
            ? null
            : normalizeThemeColors(result.workspace_skin ?? CRM_APPEARANCE_DEFAULTS.workspace_skin, source.theme_colors);
    }

    return result;
}

export function readLocalCrmAppearance() {
    if (typeof window === 'undefined') {
        return {};
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return {};
        }

        const parsed = JSON.parse(raw);

        return typeof parsed === 'object' && parsed !== null ? parsed : {};
    } catch {
        return {};
    }
}

/**
 * @param {Record<string, unknown>} appearance
 */
export function writeLocalCrmAppearance(appearance) {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        const picked = pickAppearanceFields(appearance);
        if (Object.prototype.hasOwnProperty.call(appearance, 'theme_colors') && appearance.theme_colors === null) {
            picked.theme_colors = null;
        }
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(picked));
        window.dispatchEvent(new CustomEvent(CRM_APPEARANCE_CHANGED, { detail: appearance }));
    } catch {
        /* ignore */
    }
}

function clearThemeColorProperties(html) {
    for (const field of CRM_COLOR_FIELDS) {
        html.style.removeProperty(field.css);
    }
}

/**
 * @param {Record<string, unknown>} appearance
 */
export function applyCrmAppearanceToDocument(appearance) {
    if (typeof document === 'undefined') {
        return;
    }

    const html = document.documentElement;
    const resolved = {
        ...CRM_APPEARANCE_DEFAULTS,
        ...pickAppearanceFields(appearance),
    };

    html.dataset.crmRadius = resolved.button_radius;
    html.dataset.crmAccent = resolved.primary_accent;
    html.dataset.crmTabStyle = resolved.tab_style;
    html.dataset.crmWorkspaceSkin = resolved.workspace_skin;
    html.dataset.crmNavIcons = resolved.nav_icon_style;

    const dark = documentPrefersDarkColorScheme();
    html.style.colorScheme = dark ? 'dark' : 'light';

    // Always paint effective palette (preset or overrides). Relying only on
    // CSS [data-crm-workspace-skin=…] tokens left preset clicks looking dead.
    // Must follow light/dark: inline light tokens previously overrode .dark CSS
    // and left dark:text-* labels on white surfaces.
    const colors = effectiveThemeColors(resolved, { dark });
    clearThemeColorProperties(html);

    for (const field of CRM_COLOR_FIELDS) {
        const channels = hexToRgbChannels(colors[field.key]);
        if (channels) {
            html.style.setProperty(field.css, channels);
        }
    }
}

function syncCsrfHeaderFromCookie() {
    if (typeof document === 'undefined') {
        return;
    }

    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    if (!match?.[1]) {
        return;
    }

    const token = decodeURIComponent(match[1]);
    const meta = document.querySelector('meta[name="csrf-token"]');

    if (meta) {
        meta.setAttribute('content', token);
    }

    if (window.axios?.defaults?.headers?.common) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    }
}

/**
 * @param {Record<string, unknown>} payload
 * @param {{ onSuccess?: () => void, onError?: () => void }} [callbacks]
 */
export function persistCrmAppearance(payload, callbacks = {}) {
    if (typeof window === 'undefined') {
        return;
    }

    syncCsrfHeaderFromCookie();

    router.patch('/profile/ui-preferences', payload, {
        preserveScroll: true,
        onSuccess: () => {
            callbacks.onSuccess?.();
        },
        onError: () => {
            syncCsrfHeaderFromCookie();
            router.patch('/profile/ui-preferences', payload, {
                preserveScroll: true,
                onSuccess: () => callbacks.onSuccess?.(),
                onError: () => callbacks.onError?.(),
            });
        },
    });
}

export function schedulePersistCrmAppearance(payload) {
    if (typeof window === 'undefined') {
        return;
    }

    window.clearTimeout(persistTimer);
    persistTimer = window.setTimeout(() => {
        persistCrmAppearance(payload);
    }, 450);
}

/**
 * @param {boolean} isActive
 * @param {{ tab_style?: string }} [appearance]
 */
export function crmTabButtonClasses(isActive, appearance = null) {
    const tabStyle = appearance?.tab_style
        ?? document.documentElement.dataset.crmTabStyle
        ?? CRM_APPEARANCE_DEFAULTS.tab_style;

    const classes = ['crm-tab-btn'];

    if (isActive) {
        classes.push('crm-tab-btn--active-filled');
    } else {
        classes.push('crm-tab-btn--inactive');
    }

    if (tabStyle === 'underline' && isActive) {
        /* active-filled already styled for underline via html[data-crm-tab-style] */
    }

    return classes.join(' ');
}
