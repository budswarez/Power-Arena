<?php
declare(strict_types=1);

use Arena\Preview;

class PreviewTest extends WP_UnitTestCase {
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
