<?php

namespace Tests\Unit;

use App\Support\CrmAppearance;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CrmAppearanceTest extends TestCase
{
    #[Test]
    public function it_resolves_defaults_for_empty_preferences(): void
    {
        $resolved = CrmAppearance::resolve(null);

        $this->assertSame('rounded', $resolved['button_radius']);
        $this->assertSame('sky', $resolved['primary_accent']);
        $this->assertSame('filled', $resolved['tab_style']);
        $this->assertSame('sky', $resolved['workspace_skin']);
        $this->assertSame('monochrome', $resolved['nav_icon_style']);
        $this->assertSame('normal', $resolved['ag_grid_density']);
        $this->assertNull($resolved['theme_colors']);
    }

    #[Test]
    public function it_accepts_semantic_nav_icon_style(): void
    {
        $resolved = CrmAppearance::resolve(['nav_icon_style' => 'semantic']);

        $this->assertSame('semantic', $resolved['nav_icon_style']);
    }

    #[Test]
    public function it_rejects_unknown_nav_icon_style(): void
    {
        $resolved = CrmAppearance::resolve(['nav_icon_style' => 'rainbow']);

        $this->assertSame('monochrome', $resolved['nav_icon_style']);
    }

    #[Test]
    public function it_preserves_non_appearance_preferences_on_merge(): void
    {
        $merged = CrmAppearance::mergeValidated(
            ['nav_icon_style' => 'tinted'],
            [
                'workspace_skin' => 'sky',
                'sidebar_favorites' => ['orders', 'leads'],
            ],
        );

        $this->assertSame('tinted', $merged['nav_icon_style']);
        $this->assertSame(['orders', 'leads'], $merged['sidebar_favorites']);
    }

    #[Test]
    public function it_merges_partial_validated_preferences(): void
    {
        $merged = CrmAppearance::mergeValidated(
            ['primary_accent' => 'sky'],
            ['button_radius' => 'rounded', 'tab_style' => 'underline'],
        );

        $this->assertSame('rounded', $merged['button_radius']);
        $this->assertSame('sky', $merged['primary_accent']);
        $this->assertSame('underline', $merged['tab_style']);
        $this->assertSame('sky', $merged['workspace_skin']);
    }

    #[Test]
    public function it_accepts_sky_workspace_skin(): void
    {
        $resolved = CrmAppearance::resolve(['workspace_skin' => 'sky']);

        $this->assertSame('sky', $resolved['workspace_skin']);
    }

    #[Test]
    public function it_accepts_traklo_workspace_skin(): void
    {
        $resolved = CrmAppearance::resolve(['workspace_skin' => 'traklo']);

        $this->assertSame('traklo', $resolved['workspace_skin']);
        $this->assertContains('traklo', array_column(CrmAppearance::workspaceSkinOptions(), 'value'));
    }

    #[Test]
    public function it_accepts_expanded_palette_skins(): void
    {
        foreach (['emerald', 'violet', 'amber', 'rose', 'teal', 'indigo'] as $skin) {
            $resolved = CrmAppearance::resolve(['workspace_skin' => $skin]);

            $this->assertSame($skin, $resolved['workspace_skin']);
            $this->assertArrayHasKey($skin, CrmAppearance::PALETTES);
            $this->assertContains($skin, CrmAppearance::workspaceSkinValues());
        }
    }

    #[Test]
    public function it_stores_theme_color_overrides_only_when_changed(): void
    {
        $preset = CrmAppearance::paletteForSkin('traklo');
        $resolved = CrmAppearance::resolve([
            'workspace_skin' => 'traklo',
            'theme_colors' => $preset,
        ]);

        $this->assertNull($resolved['theme_colors']);

        $resolved = CrmAppearance::resolve([
            'workspace_skin' => 'traklo',
            'theme_colors' => array_merge($preset, ['accent' => '#ff0000']),
        ]);

        $this->assertIsArray($resolved['theme_colors']);
        $this->assertSame('#ff0000', $resolved['theme_colors']['accent']);
        $this->assertSame($preset['surface'], $resolved['theme_colors']['surface']);
    }

    #[Test]
    public function it_clears_theme_colors_on_explicit_null_merge(): void
    {
        $merged = CrmAppearance::mergeValidated(
            ['theme_colors' => null],
            [
                'workspace_skin' => 'sky',
                'theme_colors' => ['accent' => '#112233', 'accent_soft' => '#e0f2fe', 'surface' => '#ffffff', 'surface_muted' => '#fafafa', 'border' => '#e4e4e7', 'text' => '#18181b', 'text_muted' => '#52525b', 'accent_contrast' => '#ffffff'],
            ],
        );

        $this->assertNull($merged['theme_colors']);
    }

    #[Test]
    public function it_converts_hex_to_rgb_channels(): void
    {
        $this->assertSame('37 99 235', CrmAppearance::hexToRgbChannels('#2563eb'));
        $this->assertSame('37 99 235', CrmAppearance::hexToRgbChannels('#2563EB'));
        $this->assertNull(CrmAppearance::hexToRgbChannels('not-hex'));
    }
}
