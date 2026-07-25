<?php
declare(strict_types=1);

/**
 * task-native-settings: template-parts/header/branding.php's logo
 * precedence — native `custom_logo` theme_mod (Aparência → Personalizar →
 * Identidade do site) → ACF `arena_logo` option (only when ACF is active
 * AND set — simulated here via the get_field() stub in tests/bootstrap.php,
 * since the real ACF plugin isn't loaded in this environment) → the
 * text-only site-name/tagline fallback. Same full-template render strategy
 * as the other *TemplateTest suites: go_to() + load_template() the actual
 * partial, not a re-implementation of its logic.
 */
class BrandingTemplateTest extends WP_UnitTestCase {
    public function tearDown(): void {
        remove_theme_mod('custom_logo');
        parent::tearDown();
    }

    private function renderBranding(): string {
        $this->go_to(home_url('/'));

        ob_start();
        load_template(get_template_directory() . '/template-parts/header/branding.php', false);
        return (string) ob_get_clean();
    }

    public function test_falls_back_to_site_name_when_no_logo_set_anywhere(): void {
        $html = $this->renderBranding();

        $this->assertStringContainsString('site-name', $html);
        $this->assertStringContainsString('site-description', $html);
        $this->assertStringNotContainsString('custom-logo', $html);
        $this->assertStringNotContainsString('site-logo', $html);
    }

    public function test_renders_native_custom_logo_when_theme_mod_set(): void {
        $attachmentId = $this->factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/canola.jpg'
        );
        set_theme_mod('custom_logo', $attachmentId);

        $html = $this->renderBranding();

        $this->assertStringContainsString('site-branding--native-logo', $html);
        $this->assertStringContainsString('custom-logo', $html);
        $this->assertStringNotContainsString('site-name', $html);
    }

    /**
     * Native theme_mod must win even when an ACF logo is ALSO set —
     * the same precedence Arena\Options::logoId() itself enforces
     * (tests/OptionsTest.php::test_logo_id_native_theme_mod_wins_over_acf).
     */
    public function test_native_custom_logo_wins_over_acf_logo(): void {
        $nativeId = $this->factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/canola.jpg'
        );
        $acfId = $this->factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/canola.jpg'
        );
        update_option('arena_logo', (string) $acfId);
        set_theme_mod('custom_logo', $nativeId);

        $html = $this->renderBranding();

        $this->assertStringContainsString('site-branding--native-logo', $html);
        $this->assertStringNotContainsString('site-logo', $html); // that class only appears on the ACF-rendered <img>.
    }

    public function test_renders_acf_logo_when_only_acf_set(): void {
        $acfId = $this->factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/canola.jpg'
        );
        update_option('arena_logo', (string) $acfId);

        $html = $this->renderBranding();

        $this->assertStringContainsString('site-logo', $html);
        $this->assertStringNotContainsString('site-branding--native-logo', $html);
        $this->assertStringNotContainsString('site-name', $html);
    }
}
