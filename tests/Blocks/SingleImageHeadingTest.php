<?php
declare(strict_types=1);

use Arena\Blocks\SingleImageHeading;

class SingleImageHeadingTest extends WP_UnitTestCase {
    public function test_filter_title_rewrites_singleimage_heading_into_bar_and_text_spans(): void {
        $html = SingleImageHeading::filterTitle(
            '<h2 class="wpb_heading wpb_singleimage_heading">Evento Power Arena 2026</h2>',
            ['title' => 'Evento Power Arena 2026', 'extraclass' => 'wpb_singleimage_heading']
        );

        $this->assertStringContainsString('wpb_singleimage_heading', $html);
        $this->assertStringContainsString('class="sih-bar"', $html);
        $this->assertStringContainsString('class="sih-text"', $html);
        $this->assertStringContainsString('Evento Power Arena 2026', $html);
        // Exactly one <h2>, with the title text now living inside .sih-text
        // rather than as the h2's own direct text content.
        $this->assertSame(1, preg_match_all('/<h2 /', $html));
    }

    public function test_filter_title_ignores_other_titled_elements(): void {
        $original = '<h2 class="wpb_heading wpb_tta_heading">Some Tab Title</h2>';

        $html = SingleImageHeading::filterTitle($original, ['title' => 'Some Tab Title', 'extraclass' => 'wpb_tta_heading']);

        $this->assertSame($original, $html);
    }

    public function test_filter_title_passes_through_when_title_is_empty(): void {
        $original = '';

        $html = SingleImageHeading::filterTitle($original, ['title' => '', 'extraclass' => 'wpb_singleimage_heading']);

        $this->assertSame($original, $html);
    }

    public function test_filter_title_escapes_the_title(): void {
        $html = SingleImageHeading::filterTitle(
            '',
            ['title' => '<script>alert(1)</script>', 'extraclass' => 'wpb_singleimage_heading']
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_register_hooks_wpb_widget_title_filter_and_it_fires(): void {
        SingleImageHeading::register();

        $filtered = apply_filters('wpb_widget_title', '<h2 class="wpb_heading wpb_singleimage_heading">Title</h2>', [
            'title'      => 'Title',
            'extraclass' => 'wpb_singleimage_heading',
        ]);

        $this->assertStringContainsString('sih-bar', $filtered);
    }
}
