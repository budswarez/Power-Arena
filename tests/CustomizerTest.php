<?php
declare(strict_types=1);

use Arena\Customizer;
use Arena\Options;

/**
 * task-native-settings: Arena\Customizer registers the theme's primary,
 * zero-plugin configuration surface (panel "Arena" → section "Opções do
 * Arena" → accent colour / sidebar position / base font controls). The
 * logo itself is NOT registered here — it rides WordPress' own built-in
 * "Identidade do site" section, added automatically by
 * `add_theme_support('custom-logo', …)` (Arena\Setup), so there's nothing
 * of this class's own to assert about it.
 */
class CustomizerTest extends WP_UnitTestCase {
    private function manager(): WP_Customize_Manager {
        require_once ABSPATH . 'wp-includes/class-wp-customize-manager.php';
        $manager = new WP_Customize_Manager();
        Customizer::boot($manager);
        return $manager;
    }

    public function test_registers_arena_panel(): void {
        $manager = $this->manager();
        $panel = $manager->get_panel(Customizer::PANEL_ID);

        $this->assertNotNull($panel);
        $this->assertSame('Arena', $panel->title);
    }

    public function test_registers_settings_section_under_arena_panel(): void {
        $manager = $this->manager();
        $section = $manager->get_section(Customizer::SECTION_ID);

        $this->assertNotNull($section);
        $this->assertSame(Customizer::PANEL_ID, $section->panel);
    }

    public function test_registers_accent_color_control(): void {
        $manager = $this->manager();

        $setting = $manager->get_setting('arena_accent_color');
        $control = $manager->get_control('arena_accent_color');

        $this->assertNotNull($setting);
        $this->assertSame('theme_mod', $setting->type);
        $this->assertSame('refresh', $setting->transport);
        $this->assertSame(Options::DEFAULT_ACCENT, $setting->default);

        $this->assertInstanceOf(WP_Customize_Color_Control::class, $control);
        $this->assertSame(Customizer::SECTION_ID, $control->section);
    }

    public function test_registers_sidebar_position_control(): void {
        $manager = $this->manager();

        $setting = $manager->get_setting('arena_sidebar_position');
        $control = $manager->get_control('arena_sidebar_position');

        $this->assertNotNull($setting);
        $this->assertSame('theme_mod', $setting->type);
        $this->assertSame(Options::DEFAULT_SIDEBAR_POSITION, $setting->default);

        $this->assertNotNull($control);
        $this->assertSame('select', $control->type);
        $this->assertSame(Options::SIDEBAR_POSITIONS, array_keys($control->choices));
    }

    public function test_registers_base_font_control(): void {
        $manager = $this->manager();

        $setting = $manager->get_setting('arena_base_font');
        $control = $manager->get_control('arena_base_font');

        $this->assertNotNull($setting);
        $this->assertSame('theme_mod', $setting->type);
        $this->assertSame(Options::DEFAULT_BASE_FONT, $setting->default);

        $this->assertNotNull($control);
        $this->assertSame('select', $control->type);
        $this->assertSame(array_keys(Options::FONT_STACKS), array_keys($control->choices));
    }

    public function test_register_hooks_boot_onto_customize_register(): void {
        Customizer::register();
        $this->assertNotFalse(has_action('customize_register', [Customizer::class, 'boot']));
    }

    // ------------------------------------------------------------------
    // Pure sanitiser unit tests (TDD'd independently of the Customizer
    // manager plumbing above).
    // ------------------------------------------------------------------

    public function test_sanitize_accent_color_accepts_valid_hex(): void {
        $this->assertSame('#00aa55', Customizer::sanitizeAccentColor('#00aa55'));
    }

    public function test_sanitize_accent_color_accepts_short_hex(): void {
        $this->assertSame('#0a5', Customizer::sanitizeAccentColor('#0a5'));
    }

    /** @dataProvider provideInvalidAccentColors */
    public function test_sanitize_accent_color_rejects_invalid_input($value): void {
        $this->assertSame(Options::DEFAULT_ACCENT, Customizer::sanitizeAccentColor($value));
    }

    public static function provideInvalidAccentColors(): array {
        return [
            ['not-a-color'],
            ['red'],
            [''],
            [123],
            [null],
            ['<script>alert(1)</script>'],
        ];
    }

    public function test_sanitize_sidebar_position_accepts_whitelisted_values(): void {
        $this->assertSame('left', Customizer::sanitizeSidebarPosition('left'));
        $this->assertSame('right', Customizer::sanitizeSidebarPosition('right'));
        $this->assertSame('none', Customizer::sanitizeSidebarPosition('none'));
    }

    public function test_sanitize_sidebar_position_rejects_unknown_value(): void {
        $this->assertSame(Options::DEFAULT_SIDEBAR_POSITION, Customizer::sanitizeSidebarPosition('bogus'));
        $this->assertSame(Options::DEFAULT_SIDEBAR_POSITION, Customizer::sanitizeSidebarPosition(''));
    }

    public function test_sanitize_base_font_accepts_whitelisted_values(): void {
        $this->assertSame('system', Customizer::sanitizeBaseFont('system'));
        $this->assertSame('barlow-oswald', Customizer::sanitizeBaseFont('barlow-oswald'));
    }

    public function test_sanitize_base_font_rejects_unknown_value(): void {
        $this->assertSame(Options::DEFAULT_BASE_FONT, Customizer::sanitizeBaseFont('comic-sans'));
    }
}
