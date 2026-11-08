<?php

namespace App\Support;

final class CrmAppearance
{
    public const BUTTON_RADIUS_SHARP = 'sharp';

    public const BUTTON_RADIUS_ROUNDED = 'rounded';

    public const PRIMARY_ACCENT_EMERALD = 'emerald';

    public const PRIMARY_ACCENT_SKY = 'sky';

    public const TAB_STYLE_FILLED = 'filled';

    public const TAB_STYLE_UNDERLINE = 'underline';

    public const WORKSPACE_SKIN_CLASSIC = 'classic';

    public const WORKSPACE_SKIN_SKY = 'sky';

    public const WORKSPACE_SKIN_TRAKLO = 'traklo';

    public const WORKSPACE_SKIN_EMERALD = 'emerald';

    public const WORKSPACE_SKIN_VIOLET = 'violet';

    public const WORKSPACE_SKIN_AMBER = 'amber';

    public const WORKSPACE_SKIN_ROSE = 'rose';

    public const WORKSPACE_SKIN_TEAL = 'teal';

    public const WORKSPACE_SKIN_INDIGO = 'indigo';

    public const NAV_ICON_STYLE_MONOCHROME = 'monochrome';

    public const NAV_ICON_STYLE_SEMANTIC = 'semantic';

    public const NAV_ICON_STYLE_TINTED = 'tinted';

    /**
     * @var list<array{key: string, label: string, css: string}>
     */
    public const COLOR_FIELDS = [
        ['key' => 'accent', 'label' => 'Акцент', 'css' => '--crm-accent'],
        ['key' => 'accent_soft', 'label' => 'Акцент, фон', 'css' => '--crm-accent-soft'],
        ['key' => 'surface', 'label' => 'Поверхность', 'css' => '--crm-surface'],
        ['key' => 'surface_muted', 'label' => 'Фон', 'css' => '--crm-surface-muted'],
        ['key' => 'border', 'label' => 'Линии', 'css' => '--crm-border'],
        ['key' => 'text', 'label' => 'Текст', 'css' => '--crm-text'],
        ['key' => 'text_muted', 'label' => 'Подписи', 'css' => '--crm-text-muted'],
        ['key' => 'accent_contrast', 'label' => 'Текст на акценте', 'css' => '--crm-accent-contrast'],
    ];

    /**
     * Preset palettes as #RRGGBB (CSS apply converts to RGB channels).
     *
     * @var array<string, array<string, string>>
     */
    public const PALETTES = [
        self::WORKSPACE_SKIN_CLASSIC => [
            'accent' => '#71717a',
            'accent_soft' => '#f4f4f5',
            'surface' => '#ffffff',
            'surface_muted' => '#fafafa',
            'border' => '#e4e4e7',
            'text' => '#18181b',
            'text_muted' => '#52525b',
            'accent_contrast' => '#ffffff',
        ],
        self::WORKSPACE_SKIN_SKY => [
            'accent' => '#0284c7',
            'accent_soft' => '#e0f2fe',
            'surface' => '#ffffff',
            'surface_muted' => '#fafafa',
            'border' => '#e4e4e7',
            'text' => '#18181b',
            'text_muted' => '#52525b',
            'accent_contrast' => '#ffffff',
        ],
        self::WORKSPACE_SKIN_TRAKLO => [
            'accent' => '#2563eb',
            'accent_soft' => '#dbeafe',
            'surface' => '#ffffff',
            'surface_muted' => '#f8fafc',
            'border' => '#e2e8f0',
            'text' => '#0f172a',
            'text_muted' => '#475569',
            'accent_contrast' => '#ffffff',
        ],
        self::WORKSPACE_SKIN_EMERALD => [
            'accent' => '#059669',
            'accent_soft' => '#d1fae5',
            'surface' => '#ffffff',
            'surface_muted' => '#fafafa',
            'border' => '#e4e4e7',
            'text' => '#18181b',
            'text_muted' => '#52525b',
            'accent_contrast' => '#ffffff',
        ],
        self::WORKSPACE_SKIN_VIOLET => [
            'accent' => '#7c3aed',
            'accent_soft' => '#ede9fe',
            'surface' => '#ffffff',
            'surface_muted' => '#fafafa',
            'border' => '#e4e4e7',
            'text' => '#18181b',
            'text_muted' => '#52525b',
            'accent_contrast' => '#ffffff',
        ],
        self::WORKSPACE_SKIN_AMBER => [
            'accent' => '#d97706',
            'accent_soft' => '#fef3c7',
            'surface' => '#ffffff',
            'surface_muted' => '#fafafa',
            'border' => '#e4e4e7',
            'text' => '#18181b',
            'text_muted' => '#52525b',
            'accent_contrast' => '#ffffff',
        ],
        self::WORKSPACE_SKIN_ROSE => [
            'accent' => '#e11d48',
            'accent_soft' => '#ffe4e6',
            'surface' => '#ffffff',
            'surface_muted' => '#fafafa',
            'border' => '#e4e4e7',
            'text' => '#18181b',
            'text_muted' => '#52525b',
            'accent_contrast' => '#ffffff',
        ],
        self::WORKSPACE_SKIN_TEAL => [
            'accent' => '#0d9488',
            'accent_soft' => '#ccfbf1',
            'surface' => '#ffffff',
            'surface_muted' => '#fafafa',
            'border' => '#e4e4e7',
            'text' => '#18181b',
            'text_muted' => '#52525b',
            'accent_contrast' => '#ffffff',
        ],
        self::WORKSPACE_SKIN_INDIGO => [
            'accent' => '#4f46e5',
            'accent_soft' => '#e0e7ff',
            'surface' => '#ffffff',
            'surface_muted' => '#fafafa',
            'border' => '#e4e4e7',
            'text' => '#18181b',
            'text_muted' => '#52525b',
            'accent_contrast' => '#ffffff',
        ],
    ];

    /**
     * @return array{button_radius: string, primary_accent: string, tab_style: string, workspace_skin: string, nav_icon_style: string, ag_grid_density: string, theme_colors: null}
     */
    public static function defaults(): array
    {
        return [
            'button_radius' => self::BUTTON_RADIUS_ROUNDED,
            'primary_accent' => self::PRIMARY_ACCENT_SKY,
            'tab_style' => self::TAB_STYLE_FILLED,
            'workspace_skin' => self::WORKSPACE_SKIN_SKY,
            'nav_icon_style' => self::NAV_ICON_STYLE_MONOCHROME,
            'ag_grid_density' => 'normal',
            'theme_colors' => null,
        ];
    }

    /**
     * @return list<string>
     */
    public static function colorKeys(): array
    {
        return array_column(self::COLOR_FIELDS, 'key');
    }

    /**
     * @return array<string, string>
     */
    public static function paletteForSkin(string $skin): array
    {
        $skin = self::normalizeWorkspaceSkin($skin) ?? self::WORKSPACE_SKIN_SKY;

        return self::PALETTES[$skin];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function buttonRadiusOptions(): array
    {
        return [
            ['value' => self::BUTTON_RADIUS_ROUNDED, 'label' => 'Скруглённые'],
            ['value' => self::BUTTON_RADIUS_SHARP, 'label' => 'Прямые углы'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function primaryAccentOptions(): array
    {
        return [
            ['value' => self::PRIMARY_ACCENT_EMERALD, 'label' => 'Зелёные'],
            ['value' => self::PRIMARY_ACCENT_SKY, 'label' => 'Голубые'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function tabStyleOptions(): array
    {
        return [
            ['value' => self::TAB_STYLE_FILLED, 'label' => 'Заливка'],
            ['value' => self::TAB_STYLE_UNDERLINE, 'label' => 'Подчёркивание'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function workspaceSkinValues(): array
    {
        return array_keys(self::PALETTES);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function workspaceSkinOptions(): array
    {
        return [
            ['value' => self::WORKSPACE_SKIN_CLASSIC, 'label' => 'Классический'],
            ['value' => self::WORKSPACE_SKIN_SKY, 'label' => 'Sky'],
            ['value' => self::WORKSPACE_SKIN_TRAKLO, 'label' => 'Traklo'],
            ['value' => self::WORKSPACE_SKIN_EMERALD, 'label' => 'Изумруд'],
            ['value' => self::WORKSPACE_SKIN_VIOLET, 'label' => 'Фиолет'],
            ['value' => self::WORKSPACE_SKIN_AMBER, 'label' => 'Янтарь'],
            ['value' => self::WORKSPACE_SKIN_ROSE, 'label' => 'Роза'],
            ['value' => self::WORKSPACE_SKIN_TEAL, 'label' => 'Бирюза'],
            ['value' => self::WORKSPACE_SKIN_INDIGO, 'label' => 'Индиго'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function navIconStyleOptions(): array
    {
        return [
            ['value' => self::NAV_ICON_STYLE_MONOCHROME, 'label' => 'Монохромные'],
            ['value' => self::NAV_ICON_STYLE_SEMANTIC, 'label' => 'Цвет по разделу'],
            ['value' => self::NAV_ICON_STYLE_TINTED, 'label' => 'С цветной подложкой'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array{button_radius: string, primary_accent: string, tab_style: string, workspace_skin: string, nav_icon_style: string, ag_grid_density: string, theme_colors: array<string, string>|null}
     */
    public static function resolve(?array $stored): array
    {
        $defaults = self::defaults();
        $stored = is_array($stored) ? $stored : [];
        $skin = self::normalizeWorkspaceSkin($stored['workspace_skin'] ?? null) ?? $defaults['workspace_skin'];

        return [
            'button_radius' => self::normalizeButtonRadius($stored['button_radius'] ?? null) ?? $defaults['button_radius'],
            'primary_accent' => self::normalizePrimaryAccent($stored['primary_accent'] ?? null) ?? $defaults['primary_accent'],
            'tab_style' => self::normalizeTabStyle($stored['tab_style'] ?? null) ?? $defaults['tab_style'],
            'workspace_skin' => $skin,
            'nav_icon_style' => self::normalizeNavIconStyle($stored['nav_icon_style'] ?? null) ?? $defaults['nav_icon_style'],
            'ag_grid_density' => self::normalizeAgGridDensity($stored['ag_grid_density'] ?? null) ?? $defaults['ag_grid_density'],
            'theme_colors' => self::normalizeThemeColors($skin, $stored['theme_colors'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    public static function mergeValidated(array $validated, ?array $existing): array
    {
        $existing = is_array($existing) ? $existing : [];
        $resolved = self::resolve($existing);

        $next = array_merge(
            $existing,
            $resolved,
            array_filter([
                'button_radius' => isset($validated['button_radius'])
                    ? self::normalizeButtonRadius($validated['button_radius'])
                    : null,
                'primary_accent' => isset($validated['primary_accent'])
                    ? self::normalizePrimaryAccent($validated['primary_accent'])
                    : null,
                'tab_style' => isset($validated['tab_style'])
                    ? self::normalizeTabStyle($validated['tab_style'])
                    : null,
                'workspace_skin' => isset($validated['workspace_skin'])
                    ? self::normalizeWorkspaceSkin($validated['workspace_skin'])
                    : null,
                'nav_icon_style' => isset($validated['nav_icon_style'])
                    ? self::normalizeNavIconStyle($validated['nav_icon_style'])
                    : null,
                'ag_grid_density' => isset($validated['ag_grid_density'])
                    ? self::normalizeAgGridDensity($validated['ag_grid_density'])
                    : null,
            ], fn (?string $value): bool => $value !== null),
        );

        if (array_key_exists('theme_colors', $validated)) {
            $skin = (string) $next['workspace_skin'];
            $next['theme_colors'] = self::normalizeThemeColors($skin, $validated['theme_colors']);
        }

        return $next;
    }

    /**
     * Returns null when colors match the preset (or invalid/empty).
     *
     * @return array<string, string>|null
     */
    public static function normalizeThemeColors(string $skin, mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            return null;
        }

        $preset = self::paletteForSkin($skin);
        $next = $preset;
        $changed = false;

        foreach (self::colorKeys() as $key) {
            $item = $value[$key] ?? null;
            if (! is_string($item)) {
                continue;
            }

            $hex = self::normalizeHex($item);
            if ($hex === null) {
                continue;
            }

            $next[$key] = $hex;
            if (strcasecmp($hex, $preset[$key]) !== 0) {
                $changed = true;
            }
        }

        return $changed ? $next : null;
    }

    public static function normalizeHex(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            return null;
        }

        return strtolower($value);
    }

    /**
     * Convert #RRGGBB to CSS channel triple "r g b" used by --crm-* tokens.
     */
    public static function hexToRgbChannels(string $hex): ?string
    {
        $hex = self::normalizeHex($hex);
        if ($hex === null) {
            return null;
        }

        $int = hexdec(substr($hex, 1));
        $r = ($int >> 16) & 255;
        $g = ($int >> 8) & 255;
        $b = $int & 255;

        return "{$r} {$g} {$b}";
    }

    private static function normalizeButtonRadius(mixed $value): ?string
    {
        return in_array($value, [self::BUTTON_RADIUS_SHARP, self::BUTTON_RADIUS_ROUNDED], true)
            ? (string) $value
            : null;
    }

    private static function normalizePrimaryAccent(mixed $value): ?string
    {
        return in_array($value, [self::PRIMARY_ACCENT_EMERALD, self::PRIMARY_ACCENT_SKY], true)
            ? (string) $value
            : null;
    }

    private static function normalizeTabStyle(mixed $value): ?string
    {
        return in_array($value, [self::TAB_STYLE_FILLED, self::TAB_STYLE_UNDERLINE], true)
            ? (string) $value
            : null;
    }

    private static function normalizeWorkspaceSkin(mixed $value): ?string
    {
        return in_array($value, self::workspaceSkinValues(), true)
            ? (string) $value
            : null;
    }

    private static function normalizeNavIconStyle(mixed $value): ?string
    {
        return in_array($value, [
            self::NAV_ICON_STYLE_MONOCHROME,
            self::NAV_ICON_STYLE_SEMANTIC,
            self::NAV_ICON_STYLE_TINTED,
        ], true)
            ? (string) $value
            : null;
    }

    private static function normalizeAgGridDensity(mixed $value): ?string
    {
        return in_array($value, ['compact', 'normal', 'comfortable'], true)
            ? (string) $value
            : null;
    }
}
