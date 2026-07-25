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

    // ------------------------------------------------------------------
    // FIX 1 (task-review-fixes-3): accessible-accent derivation.
    // Contrast maths per WCAG 2.x: relative luminance with sRGB gamma
    // correction, ratio (L1+.05)/(L2+.05) with L1 >= L2. Against a WHITE
    // background/foreground, L1 is always 1.0, so contrastWithWhite()
    // collapses to (1.05)/(L_color+.05) — verified below against known
    // reference values (#000000 -> 21:1, #ffffff -> 1:1, the widely-cited
    // WCAG boundary gray #767676 -> ~4.5:1).
    // ------------------------------------------------------------------

    public function test_contrast_with_white_matches_known_ratio_for_black(): void {
        $this->assertEqualsWithDelta(21.0, Options::contrastWithWhite('#000000'), 0.01);
    }

    public function test_contrast_with_white_matches_known_ratio_for_white(): void {
        $this->assertEqualsWithDelta(1.0, Options::contrastWithWhite('#ffffff'), 0.01);
    }

    public function test_contrast_with_white_matches_known_boundary_gray(): void {
        $this->assertEqualsWithDelta(4.5, Options::contrastWithWhite('#767676'), 0.05);
    }

    public function test_contrast_with_white_accepts_short_hex(): void {
        $this->assertEqualsWithDelta(Options::contrastWithWhite('#000000'), Options::contrastWithWhite('#000'), 0.01);
    }

    /** Already-accessible colours must be returned unchanged (normalized), never darkened further. */
    public function test_accessible_text_color_returns_unchanged_when_already_passing(): void {
        $this->assertSame('#000000', Options::accessibleTextColor('#000000'));
        $this->assertSame('#003791', Options::accessibleTextColor('#003791'));
    }

    /**
     * A battery of failing colours (white, a pale pastel, the theme's own
     * raw accent, saturated green/yellow) must all be darkened until they
     * clear the 4.5:1 AA small-text threshold against white.
     *
     * @dataProvider provideFailingColors
     */
    public function test_accessible_text_color_darkens_failing_colors_until_they_pass(string $hex): void {
        $derived = Options::accessibleTextColor($hex);
        $this->assertGreaterThanOrEqual(4.5, Options::contrastWithWhite($derived), "derived colour for {$hex} must clear 4.5:1");
    }

    /** @return array<int, array{0: string}> */
    public static function provideFailingColors(): array {
        return [
            ['#ffffff'],
            ['#ffe0b2'],
            ['#f42c1a'], // the theme's own raw accent — documented as 4.02:1, failing.
            ['#00ff00'],
            ['#ffff00'],
        ];
    }

    public function test_accessible_text_color_normalizes_short_hex(): void {
        $derived = Options::accessibleTextColor('#fff');
        $this->assertGreaterThanOrEqual(4.5, Options::contrastWithWhite($derived));
    }

    /** Malformed/non-hex input must never crash — falls back to a value that still clears AA. */
    public function test_accessible_text_color_falls_back_for_invalid_input(): void {
        $derived = Options::accessibleTextColor('not-a-color');
        $this->assertGreaterThanOrEqual(4.5, Options::contrastWithWhite($derived));
    }

    public function test_accessible_text_color_respects_custom_min_ratio(): void {
        // The raw accent (#f42c1a) already clears a lenient 3:1 bar
        // (documented ~4.02:1), so a lenient call must return it UNCHANGED,
        // while the default 4.5:1 bar must darken it further.
        $strict = Options::accessibleTextColor('#f42c1a', 4.5);
        $lenient = Options::accessibleTextColor('#f42c1a', 3.0);
        $this->assertSame('#f42c1a', $lenient);
        $this->assertNotSame('#f42c1a', $strict);
        $this->assertGreaterThanOrEqual(3.0, Options::contrastWithWhite($lenient));
        $this->assertGreaterThanOrEqual(4.5, Options::contrastWithWhite($strict));
    }

    // ------------------------------------------------------------------
    // FIX 1: sidebar position -> Layout shell key mapping.
    // ------------------------------------------------------------------

    public function test_map_sidebar_position_to_layout(): void {
        $this->assertSame('2col-right', Options::mapSidebarPositionToLayout('right'));
        $this->assertSame('2col-left', Options::mapSidebarPositionToLayout('left'));
        $this->assertSame('1col', Options::mapSidebarPositionToLayout('none'));
    }

    public function test_map_sidebar_position_to_layout_falls_back_to_right_for_unknown(): void {
        $this->assertSame('2col-right', Options::mapSidebarPositionToLayout('bogus'));
    }

    public function test_sidebar_position_defaults_to_right_without_acf(): void {
        $this->assertSame('right', Options::sidebarPosition());
    }

    public function test_sidebar_layout_defaults_to_two_col_right_without_acf(): void {
        $this->assertSame('2col-right', Options::sidebarLayout());
    }

    // ------------------------------------------------------------------
    // FIX 1: base font whitelist (arena_base_font).
    // ------------------------------------------------------------------

    public function test_base_font_defaults_to_barlow_oswald_without_acf(): void {
        $this->assertSame('barlow-oswald', Options::baseFont());
    }

    public function test_font_stack_returns_theme_pairing_for_default_key(): void {
        $stack = Options::fontStack('barlow-oswald');
        $this->assertSame("'Barlow', sans-serif", $stack['body']);
        $this->assertSame("'Oswald', sans-serif", $stack['head']);
    }

    public function test_font_stack_returns_system_stack_for_system_key(): void {
        $stack = Options::fontStack('system');
        $this->assertStringNotContainsString('Barlow', $stack['body']);
        $this->assertStringNotContainsString('Oswald', $stack['head']);
    }

    public function test_font_stack_falls_back_to_default_for_unknown_key(): void {
        $this->assertSame(Options::fontStack('barlow-oswald'), Options::fontStack('bogus-key'));
    }

    // ------------------------------------------------------------------
    // FIX 1: assembled CSS custom-property tokens (wp_head inline block).
    // ------------------------------------------------------------------

    public function test_css_tokens_shape_without_acf(): void {
        $tokens = Options::cssTokens();
        $this->assertSame(Options::DEFAULT_ACCENT, $tokens['--arena-accent']);
        $this->assertGreaterThanOrEqual(4.5, Options::contrastWithWhite($tokens['--arena-accent-text']));
        $this->assertSame("'Barlow', sans-serif", $tokens['--arena-font-body']);
        $this->assertSame("'Oswald', sans-serif", $tokens['--arena-font-head']);
    }

    /**
     * cssTokens() must derive `--arena-accent-text` with a small safety
     * margin ABOVE the bare 4.5:1 minimum (SAFE_ACCESSIBLE_CONTRAST) — the
     * bare-minimum derivation can land razor-thin (e.g. ~4.51:1 for the
     * theme's own default accent), too close to the line to trust across a
     * real browser's rounding during an automated contrast audit.
     */
    public function test_css_tokens_accent_text_has_safety_margin_above_bare_minimum(): void {
        $tokens = Options::cssTokens();
        $this->assertGreaterThanOrEqual(
            Options::SAFE_ACCESSIBLE_CONTRAST,
            Options::contrastWithWhite($tokens['--arena-accent-text'])
        );
    }
}
