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

    /**
     * The default must match the real accent baked into main.css
     * (`--arena-accent: #f42c1a`) — not the `#e00000` literal that used to
     * be duplicated (and one hex value off) across Options/OptionsPanel.
     */
    public function test_accent_color_default_matches_shared_constant(): void {
        $this->assertSame('#f42c1a', Options::DEFAULT_ACCENT);
        $this->assertSame(Options::DEFAULT_ACCENT, Options::accentColor());
    }

    public function test_logo_id_null_by_default(): void {
        $this->assertNull(Options::logoId());
    }

    /**
     * `is_numeric()` used to accept float-strings like "3.9", which can
     * never be a real attachment ID. Options::parseLogoId() — the pure
     * logic logoId() delegates to — must reject anything that isn't a
     * clean positive-integer string, and accept a clean one.
     */
    public function test_parse_logo_id_rejects_float_strings_and_non_numeric(): void {
        $this->assertNull(Options::parseLogoId('3.9'));
        $this->assertNull(Options::parseLogoId('abc'));
    }

    public function test_parse_logo_id_accepts_a_clean_integer_string(): void {
        $this->assertSame(12, Options::parseLogoId('12'));
    }
}
