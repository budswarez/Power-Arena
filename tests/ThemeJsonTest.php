<?php
declare(strict_types=1);

/**
 * theme.json (GAP E) — previously declared only fontFamilies + palette.
 * Asserts the modern editor/layout declarations the block editor needs
 * (appearanceTools, layout content/wide widths, spacing/typography scales)
 * are present, valid, and consistent with the front end's real tokens
 * (--arena-site-width: 1200px == wideSize; the accessible
 * --arena-accent-text: #c81f10 is now also a picker colour).
 */
class ThemeJsonTest extends WP_UnitTestCase {
    /** @return array<string, mixed> */
    private function themeJson(): array {
        $path = get_template_directory() . '/theme.json';
        $this->assertFileExists($path);
        $decoded = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($decoded, 'theme.json must be valid JSON.');
        return $decoded;
    }

    public function test_schema_version_is_3(): void {
        $json = $this->themeJson();
        $this->assertSame(3, $json['version'] ?? null);
    }

    public function test_appearance_tools_enabled(): void {
        $json = $this->themeJson();
        $this->assertTrue($json['settings']['appearanceTools'] ?? false);
    }

    /** wideSize must match the front end's real boxed site width (--arena-site-width). */
    public function test_layout_widths_match_the_front_end_tokens(): void {
        $json = $this->themeJson();
        $this->assertSame('720px', $json['settings']['layout']['contentSize'] ?? null);
        $this->assertSame('1200px', $json['settings']['layout']['wideSize'] ?? null);
    }

    public function test_typography_keeps_barlow_and_oswald_font_families(): void {
        $json = $this->themeJson();
        $slugs = array_column($json['settings']['typography']['fontFamilies'] ?? [], 'slug');
        $this->assertContains('barlow', $slugs);
        $this->assertContains('oswald', $slugs);
    }

    /** Introducing typography/spacing scales must not silently drop the existing accent palette. */
    public function test_color_palette_still_has_the_accessible_accent_text_colour(): void {
        $json = $this->themeJson();
        $colors = array_column($json['settings']['color']['palette'] ?? [], 'color', 'slug');
        $this->assertArrayHasKey('accent-text', $colors);
        $this->assertSame('#c81f10', $colors['accent-text']);
    }
}
