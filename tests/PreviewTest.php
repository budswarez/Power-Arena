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
}
