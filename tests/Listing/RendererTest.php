<?php
declare(strict_types=1);

use Arena\Listing\Renderer;

class RendererTest extends WP_UnitTestCase {
    public function test_render_grid_contains_post_titles_and_wrapper_class(): void {
        $titles = ['Arena Renderer Post Alpha', 'Arena Renderer Post Beta', 'Arena Renderer Post Gamma'];
        foreach ($titles as $title) {
            $this->factory()->post->create(['post_title' => $title, 'post_status' => 'publish']);
        }

        $html = Renderer::render('grid', ['count' => '3', 'columns' => '3']);

        $this->assertNotSame('', $html);
        $this->assertStringContainsString('bs-listing-grid', $html);
        $this->assertStringContainsString('columns-3', $html);
        foreach ($titles as $title) {
            $this->assertStringContainsString(esc_html($title), $html);
        }
    }

    public function test_render_blog_contains_excerpt_and_wrapper_class(): void {
        $this->factory()->post->create([
            'post_title'   => 'Arena Blog Post',
            'post_content' => 'Conteudo completo do post de teste do layout blog.',
            'post_excerpt' => 'Resumo customizado do post de teste.',
            'post_status'  => 'publish',
        ]);

        $html = Renderer::render('blog', ['count' => '1']);

        $this->assertStringContainsString('bs-listing-blog', $html);
        $this->assertStringContainsString('post-summary', $html);
        $this->assertStringContainsString('Resumo customizado do post de teste.', $html);
    }

    public function test_render_with_empty_but_real_term_returns_safe_string_without_warnings(): void {
        $term = wp_insert_term('Arena Empty Term ' . time(), 'category');
        $this->assertIsArray($term);
        $termId = (int) $term['term_id'];
        $this->assertGreaterThan(0, $termId);

        // No posts are assigned to this term, so the query is legitimately empty.
        $html = Renderer::render('grid', ['category' => (string) $termId]);

        $this->assertIsString($html);
        $this->assertStringNotContainsString('Warning', $html);
        $this->assertStringNotContainsString('Notice', $html);
        $this->assertStringNotContainsString('Fatal error', $html);
        $this->assertStringContainsString('bs-listing-grid', $html);
    }

    public function test_render_unknown_layout_falls_back_safely(): void {
        $this->factory()->post->create(['post_title' => 'Arena Fallback Post', 'post_status' => 'publish']);

        $html = Renderer::render('not-a-real-layout', ['count' => '1']);

        $this->assertIsString($html);
        $this->assertNotSame('', $html);
        $this->assertStringNotContainsString('Warning', $html);
    }

    public function test_render_mix_layout_omits_thumbnail_by_default(): void {
        $postId = $this->factory()->post->create(['post_title' => 'Arena Mix Post', 'post_status' => 'publish']);
        $this->assertIsInt($postId);

        $html = Renderer::render('mix', ['count' => '1']);

        $this->assertStringContainsString('bs-listing-mix', $html);
        $this->assertStringContainsString('Arena Mix Post', $html);
        $this->assertStringNotContainsString('img-holder', $html);
    }

    public function test_render_modern_grid_marks_first_card_for_lcp(): void {
        $postId = $this->factory()->post->create(['post_title' => 'Arena MG Post', 'post_status' => 'publish']);
        $attachmentId = $this->factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/canola.jpg',
            $postId
        );
        set_post_thumbnail($postId, $attachmentId);

        $html = Renderer::render('modern-grid', ['count' => '1']);

        $this->assertStringContainsString('bs-listing-modern-grid', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('decoding="async"', $html);
        $this->assertMatchesRegularExpression('/<img[^>]*width="\d+"/', $html);
        $this->assertMatchesRegularExpression('/<img[^>]*height="\d+"/', $html);
    }

    public function test_render_honors_heading_title_and_color(): void {
        $this->factory()->post->create(['post_title' => 'Arena Heading Post', 'post_status' => 'publish']);

        $html = Renderer::render('grid', [
            'count'         => '1',
            'title'         => 'Destaques',
            'heading_color' => '#ff0000',
        ]);

        $this->assertStringContainsString('Destaques', $html);
        $this->assertStringContainsString('color:#ff0000', $html);
    }

    public function test_comment_icon_is_an_inline_svg_not_an_icon_font_glyph(): void {
        $icon = Renderer::commentIcon();

        $this->assertStringStartsWith('<svg', $icon);
        $this->assertStringContainsString('icon-comment', $icon);
        $this->assertStringContainsString('aria-hidden="true"', $icon);
        $this->assertStringNotContainsString('fa fa-comments', $icon);
        $this->assertStringNotContainsString('<i ', $icon);
    }

    public function test_render_mix_layout_first_card_shows_comment_icon_before_count(): void {
        $this->factory()->post->create(['post_title' => 'Arena Mix Comments Post', 'post_status' => 'publish']);

        $html = Renderer::render('mix', ['count' => '1']);

        $this->assertStringContainsString('class="comments"', $html);
        $this->assertStringContainsString('<svg class="icon-comment"', $html);
    }

    /**
     * The single-article meta date must render with the SAME short format
     * as the home cards (e.g. "24 Jul, 2026"), not WordPress's default
     * English long-form date_format option — both now go through this one
     * shared helper instead of each duplicating the format string.
     */
    public function test_article_date_uses_short_card_format_for_a_fixed_date(): void {
        $postId = $this->factory()->post->create([
            'post_title' => 'Arena Date Format Post',
            'post_date'  => '2026-07-24 10:00:00',
        ]);

        $this->assertSame('24 Jul, 2026', Renderer::articleDate($postId));
    }
}
