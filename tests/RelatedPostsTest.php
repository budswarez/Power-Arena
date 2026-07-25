<?php
declare(strict_types=1);

/**
 * template-parts/single/related.php — GAP 1 (real missing feature): the
 * article page's related-posts section. The reference article carries a
 * related-posts block (`infinity-related-post` in its body classes); Arena's
 * single.php previously had none at all.
 *
 * Query shape (see the partial's own docblock): posts sharing the current
 * post's primary category, excluding the current post, limited to 4, ordered
 * by date desc; falls back to recent posts (still excluding the current
 * post, no category restriction) when the category yields fewer than 2
 * posts. Cards reuse template-parts/card/blog5.php — no duplicated markup.
 */
class RelatedPostsTest extends WP_UnitTestCase {
    private function renderRelated(int $postId): string {
        $this->go_to(get_permalink($postId));
        $this->assertTrue(is_single(), 'go_to() must land on a singular post query for this test to be meaningful.');

        ob_start();
        while (have_posts()) {
            the_post();
            get_template_part('template-parts/single/related');
        }
        return (string) ob_get_clean();
    }

    public function test_shows_four_same_category_posts_excluding_current(): void {
        $term = wp_insert_term('Arena Related Category ' . time(), 'category');
        $this->assertIsArray($term);
        $categoryId = (int) $term['term_id'];
        $this->assertGreaterThan(0, $categoryId);

        $postIds = [];
        for ($i = 0; $i < 5; $i++) {
            $postIds[] = $this->factory()->post->create([
                'post_title'    => 'Arena Related Post ' . $i,
                'post_status'   => 'publish',
                'post_category' => [$categoryId],
            ]);
        }

        $currentId = $postIds[0];
        $html = $this->renderRelated($currentId);

        $this->assertSame(4, substr_count($html, 'listing-item-blog-5'), 'Exactly 4 related cards must render.');
        $this->assertStringNotContainsString('Arena Related Post 0', $html, 'The current post must never appear among its own related posts.');
        // Every other post in the fixture shares the same category, so any
        // of them proves a same-category post made it into the output.
        $this->assertStringContainsString('Arena Related Post 1', $html);
    }

    /**
     * BUG 4 (task-uifix): a related post with no thumbnail used to leave
     * an empty, non-clickable box where the thumb would be (card/list.php,
     * 'archive' variant, omitted its whole `.featured` block when
     * thumbless). Must now render the theme's own default placeholder
     * image, wrapped in a real link to that related post.
     */
    public function test_thumbless_related_post_renders_placeholder_inside_a_link_to_it(): void {
        $term = wp_insert_term('Arena Related No Thumb Category ' . time(), 'category');
        $this->assertIsArray($term);
        $categoryId = (int) $term['term_id'];

        $currentId = $this->factory()->post->create([
            'post_title'    => 'Arena Related Current',
            'post_status'   => 'publish',
            'post_category' => [$categoryId],
        ]);
        $noThumbId = $this->factory()->post->create([
            'post_title'    => 'Arena Related No Thumb',
            'post_status'   => 'publish',
            'post_category' => [$categoryId],
        ]);

        $html = $this->renderRelated($currentId);
        $permalink = get_permalink($noThumbId);
        $this->assertIsString($permalink);

        $this->assertMatchesRegularExpression(
            '#<a[^>]*class="[^"]*thumb-placeholder[^"]*"[^>]*href="' . preg_quote(esc_url($permalink), '#') . '"[^>]*>\s*<img[^>]*src="[^"]*placeholder\.svg[^"]*"#',
            $html
        );
    }

    public function test_renders_nothing_when_site_has_only_the_current_post(): void {
        $postId = $this->factory()->post->create([
            'post_title'  => 'Arena Only Post',
            'post_status' => 'publish',
        ]);

        $html = $this->renderRelated($postId);

        $this->assertSame('', $html, 'No candidates exist (category fallback also empty) — must render nothing, not an empty heading/section.');
    }
}
