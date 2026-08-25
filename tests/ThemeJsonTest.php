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

    /**
     * Whole-branch review, minor finding #11: `"version": 3` needs WP
     * 6.6+, while style.css's own `Requires at least` is 6.4 — and nothing
     * this file declares (appearanceTools/spacingSizes/color.palette/
     * fontFamilies with fontFace) actually needs v3; fontFace support
     * inside fontFamilies landed in the v2 schema back in WP 6.4.
     * Reconciled DOWN to 2 (matching the real requirement) rather than
     * bumping the site's minimum WP version up to 6.6.
     */
    public function test_schema_version_matches_the_style_css_wp_requirement(): void {
        $json = $this->themeJson();
        $this->assertSame(2, $json['version'] ?? null);
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

    /**
     * Minor finding #11: fontFamilies declared Barlow/Oswald as selectable
     * in the block editor with no `fontFace` entries backing them — the
     * editor offered fonts it could never actually render, silently
     * falling back to a substitute. Every family must now carry at least
     * one real fontFace pointing at the self-hosted assets/fonts/ file.
     */
    public function test_every_font_family_has_at_least_one_font_face(): void {
        $json = $this->themeJson();
        foreach ($json['settings']['typography']['fontFamilies'] ?? [] as $family) {
            $this->assertNotEmpty($family['fontFace'] ?? [], $family['slug'] . ' must declare at least one fontFace.');
            foreach ($family['fontFace'] as $face) {
                $this->assertNotEmpty($face['src'] ?? [], $family['slug'] . ' fontFace must have a src.');
                $src = $face['src'][0];
                $this->assertStringStartsWith('file:./assets/fonts/', $src);
                $path = get_template_directory() . '/' . substr($src, strlen('file:./'));
                $this->assertFileExists($path, "$src must point at a real self-hosted font file.");
            }
        }
    }

    /**
     * Sem unicodeRange o core imprime todas as variantes como faces globais.
     * O navegador acabava baixando latin e latin-ext do mesmo peso na home.
     */
    public function test_every_font_face_declares_a_unicode_range(): void {
        $json = $this->themeJson();
        foreach ($json['settings']['typography']['fontFamilies'] ?? [] as $family) {
            foreach ($family['fontFace'] ?? [] as $face) {
                $this->assertNotEmpty(
                    $face['unicodeRange'] ?? '',
                    ($face['src'][0] ?? 'fontFace') . ' must declare unicodeRange.'
                );
                $this->assertStringStartsWith('U+', $face['unicodeRange']);
            }
        }
    }

    /**
     * Minor finding #11: with no `styles.color.background`, the editor
     * canvas rendered white while the front end's own `body{}` (main.css)
     * is black (`background-color:#000`, `color:#fff`) — the two should
     * match.
     */
    public function test_editor_canvas_background_matches_the_front_end_body(): void {
        $json = $this->themeJson();
        $this->assertSame('#000000', $json['styles']['color']['background'] ?? null);
        $this->assertSame('#ffffff', $json['styles']['color']['text'] ?? null);
    }
}
