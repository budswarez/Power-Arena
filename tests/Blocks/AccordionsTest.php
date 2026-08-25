<?php
declare(strict_types=1);

use Arena\Blocks\Accordions;

class AccordionsTest extends WP_UnitTestCase {
    public function test_register_adds_both_shortcodes(): void {
        Accordions::register();

        $this->assertTrue(shortcode_exists('accordions'));
        $this->assertTrue(shortcode_exists('accordion'));
    }

    public function test_load_hide_renders_collapsed_details_without_open_attribute(): void {
        Accordions::register();

        $html = do_shortcode(
            '[accordions title=""][accordion title="Resumo da matéria" load="hide"]- a' . "\n" . '- b[/accordion][/accordions]'
        );

        $this->assertStringContainsString('Resumo da matéria', $html);
        $this->assertStringContainsString('<details', $html);
        $this->assertStringNotContainsString('[accordion', $html);
        $this->assertStringContainsString('- a', $html);
        $this->assertStringContainsString('- b', $html);

        // The <details> element must NOT carry the `open` attribute.
        $this->assertMatchesRegularExpression('/<details(?![^>]*\bopen\b)[^>]*>/', $html);
    }

    public function test_without_load_hide_renders_open_details(): void {
        Accordions::register();

        $html = do_shortcode(
            '[accordions title=""][accordion title="Aberto"]conteudo do painel[/accordion][/accordions]'
        );

        $this->assertMatchesRegularExpression('/<details[^>]*\bopen\b[^>]*>/', $html);
    }

    public function test_accordions_wrapper_title_renders_heading_when_non_empty(): void {
        Accordions::register();

        $html = do_shortcode('[accordions title="Sumário"][accordion title="X"]y[/accordion][/accordions]');

        $this->assertStringContainsString('Sumário', $html);
    }

    public function test_accordions_wrapper_title_renders_no_heading_when_empty(): void {
        Accordions::register();

        $html = do_shortcode('[accordions title=""][accordion title="X"]y[/accordion][/accordions]');

        $this->assertStringNotContainsString('arena-accordion__heading', $html);
    }

    public function test_nested_multiple_accordion_panels_all_render(): void {
        Accordions::register();

        $html = do_shortcode(
            '[accordions title=""]'
            . '[accordion title="Painel 1" load="hide"]conteudo 1[/accordion]'
            . '[accordion title="Painel 2"]conteudo 2[/accordion]'
            . '[/accordions]'
        );

        $this->assertStringContainsString('Painel 1', $html);
        $this->assertStringContainsString('Painel 2', $html);
        $this->assertStringContainsString('conteudo 1', $html);
        $this->assertStringContainsString('conteudo 2', $html);
        $this->assertSame(2, preg_match_all('/<details/', $html));
        $this->assertStringNotContainsString('[accordion', $html);
    }

    public function test_panel_content_is_kses_sanitized(): void {
        Accordions::register();

        $html = do_shortcode(
            '[accordions title=""][accordion title="X"]<script>alert(1)</script>texto seguro[/accordion][/accordions]'
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('texto seguro', $html);
    }

    /**
     * Minor finding #8 (whole-branch review): `the_content` runs
     * `wpautop()` (priority 10) BEFORE `do_shortcode()` (priority 11), so
     * real imported content sees the newline right after
     * `[accordions title=""]` and right before `[/accordions]` turned into
     * a literal `<br />` before this shortcode handler ever runs — that
     * stray tag used to survive straight into the rendered box.
     */
    public function test_stray_br_from_wpautop_is_stripped_from_wrapper_edges(): void {
        Accordions::register();

        $raw = "[accordions title=\"\"]\n[accordion title=\"Resumo\" load=\"hide\"]\n- item 1\n- item 2\n[/accordion]\n[/accordions]";
        $autopd = wpautop($raw);
        $html = do_shortcode($autopd);

        $wrapperStart = strpos($html, '<div class="arena-accordion">');
        $this->assertNotFalse($wrapperStart);
        $afterWrapperOpen = ltrim(substr($html, $wrapperStart + strlen('<div class="arena-accordion">'), 20));
        $this->assertFalse(str_starts_with($afterWrapperOpen, '<br'));

        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('- item 1', $html);
        $this->assertStringContainsString('- item 2', $html);
    }

    /**
     * Minor finding #8: unlike `renderAccordion()`, the wrapper previously
     * ran no sanitization at all on its own `$content` — raw HTML placed
     * directly between `[accordions]...[/accordions]`, outside any
     * `[accordion]` panel, passed straight through untouched.
     */
    public function test_wrapper_strips_disallowed_html_placed_outside_any_panel(): void {
        Accordions::register();

        $html = do_shortcode(
            '[accordions title=""]<script>alert(1)</script>[accordion title="X"]conteudo[/accordion][/accordions]'
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('conteudo', $html);
    }
}
