<?php
declare(strict_types=1);

use Arena\Preview;

class PreviewTest extends WP_UnitTestCase {
    /**
     * mu-plugins/arena-preview.php can't reference Preview::THEME directly
     * (it runs before this class's autoloader exists) and keeps its own
     * inline 'arena' literal instead — this guards that the two values
     * stay in sync, since nothing enforces it at runtime.
     */
    public function test_theme_slug_matches_mu_plugin_literal(): void {
        $this->assertSame('arena', Preview::THEME);
    }

    public function test_admin_capability_enables_preview(): void {
        $this->assertTrue(Preview::shouldPreview(true, null, 'segredo'));
    }

    public function test_matching_token_enables_preview(): void {
        $this->assertTrue(Preview::shouldPreview(false, 'segredo', 'segredo'));
    }

    public function test_wrong_token_denies(): void {
        $this->assertFalse(Preview::shouldPreview(false, 'errado', 'segredo'));
    }

    public function test_no_token_configured_denies_param_path(): void {
        $this->assertFalse(Preview::shouldPreview(false, 'qualquer', null));
    }

    /**
     * FIX D: when `ARENA_PREVIEW_STYLESHEET` isn't defined (the common
     * case), the preview must keep forcing the parent theme itself.
     */
    public function test_resolve_stylesheet_defaults_to_theme_constant_when_unconfigured(): void {
        $this->assertSame(Preview::THEME, Preview::resolveStylesheet(null));
        $this->assertSame(Preview::THEME, Preview::resolveStylesheet(''));
    }

    /**
     * FIX D: if production runs `arena-child` as the active theme,
     * `ARENA_PREVIEW_STYLESHEET=arena-child` must make the preview show
     * THAT, not the parent — otherwise the preview never previews what
     * actually ships.
     */
    public function test_resolve_stylesheet_honors_configured_value(): void {
        $this->assertSame('arena-child', Preview::resolveStylesheet('arena-child'));
    }
}
