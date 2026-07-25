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
}
