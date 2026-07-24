<?php
declare(strict_types=1);

use Arena\Options;

class OptionsTest extends WP_UnitTestCase {
    public function test_get_returns_default_when_acf_absent(): void {
        // ACF não está carregado no ambiente de teste -> deve cair no default.
        $this->assertSame('fallback', Options::get('qualquer_coisa', 'fallback'));
    }

    public function test_accent_color_has_sane_default(): void {
        $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{3,6}$/', Options::accentColor());
    }

    public function test_logo_id_null_by_default(): void {
        $this->assertNull(Options::logoId());
    }
}
