<?php
declare(strict_types=1);

use Arena\Listing\Renderer;

/**
 * Layout `modern-grid-1`: the ARCHIVE (category/tag) featured mosaic —
 * an IRREGULAR pattern (`listing-modern-grid-1` in the reference), distinct
 * from the HOME's uniform 2-large + 3-compact `modern-grid` (which stays
 * untouched, verified by RendererTest). Measured against
 * `.mg-col-1`/`.mg-col-2`/`.item-3-cont`/`.item-4-cont` in
 * ref-category.html: ONE large tile on the left (56%), and on the right
 * (44%) one medium tile on top plus two small tiles side by side beneath
 * it — 4 tiles total, reusing template-parts/card/hero.php so overlay/
 * gradient/badge/meta stay identical to the home hero cards.
 */
class ModernGrid1Test extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        // Trinco de vida-de-requisicao: sem o reset, o segundo teste do processo
        // ja encontra a vaga de 'acima da dobra' consumida.
        // Ver Arena\Media::claimAboveTheFoldBlock().
        \Arena\Media::resetAboveTheFoldBlock();
    }

    /** @return int[] post IDs, newest first (default query order). */
    private function createPostsInCategory(int $catId, int $count): array {
        $now = time();
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $postId = $this->factory()->post->create([
                'post_title'    => 'Arena MG1 Post ' . $i,
                'post_status'   => 'publish',
                'post_category' => [$catId],
                // Ensures a stable, distinct, descending publish order.
                'post_date'     => gmdate('Y-m-d H:i:s', $now - $i * 60),
            ]);
            $attachmentId = $this->factory()->attachment->create_upload_object(
                DIR_TESTDATA . '/images/canola.jpg',
                $postId
            );
            set_post_thumbnail($postId, $attachmentId);
            $ids[] = $postId;
        }

        return $ids;
    }

    public function test_render_modern_grid_1_has_two_column_wrappers_and_four_tiles_with_first_card_marked_for_lcp(): void {
        $catId = (int) self::factory()->category->create(['name' => 'Arena MG1 Category']);
        $this->createPostsInCategory($catId, 6);

        $html = Renderer::render('modern-grid-1', ['category' => (string) $catId, 'count' => '4']);

        $this->assertStringContainsString('bs-listing-modern-grid-1', $html);
        $this->assertStringContainsString('listing-modern-grid-1', $html);
        $this->assertStringContainsString('mg1-col-1', $html);
        $this->assertStringContainsString('mg1-col-2', $html);

        // Exactly 4 tiles (reusing card/hero.php's own root class).
        $this->assertSame(4, substr_count($html, 'listing-item-hero'));

        // Only the very first (large, left column) tile is the LCP image.
        $this->assertSame(1, substr_count($html, 'fetchpriority="high"'));
        $this->assertStringContainsString('decoding="async"', $html);
        $this->assertMatchesRegularExpression('/<img[^>]*width="\d+"/', $html);
        $this->assertMatchesRegularExpression('/<img[^>]*height="\d+"/', $html);

        $fetchPos = strpos($html, 'fetchpriority="high"');
        $col2Pos = strpos($html, 'mg1-col-2');
        $this->assertNotFalse($fetchPos);
        $this->assertNotFalse($col2Pos);
        // The LCP tile lives inside mg1-col-1, which precedes mg1-col-2 in the DOM.
        $this->assertLessThan($col2Pos, $fetchPos);
    }

    public function test_render_modern_grid_1_with_two_posts_renders_two_tiles_and_no_empty_tile_markup(): void {
        $catId = (int) self::factory()->category->create(['name' => 'Arena MG1 Sparse Category']);
        $this->createPostsInCategory($catId, 2);

        $html = Renderer::render('modern-grid-1', ['category' => (string) $catId, 'count' => '4']);

        $this->assertSame(2, substr_count($html, 'listing-item-hero'));
        $this->assertStringContainsString('mg1-col-1', $html);
        $this->assertStringContainsString('mg1-col-2', $html);
        // No 3rd/4th tile row should be printed at all when those posts don't exist.
        $this->assertStringNotContainsString('mg1-row-bottom', $html);
        $this->assertStringNotContainsString('Warning', $html);
        $this->assertStringNotContainsString('Fatal error', $html);
    }

    public function test_render_modern_grid_1_with_one_post_omits_the_second_column_entirely(): void {
        $catId = (int) self::factory()->category->create(['name' => 'Arena MG1 Single Category']);
        $this->createPostsInCategory($catId, 1);

        $html = Renderer::render('modern-grid-1', ['category' => (string) $catId, 'count' => '4']);

        $this->assertSame(1, substr_count($html, 'listing-item-hero'));
        $this->assertStringContainsString('mg1-col-1', $html);
        $this->assertStringNotContainsString('mg1-col-2', $html);
    }

    public function test_render_modern_grid_1_with_three_posts_shows_a_single_full_width_third_tile(): void {
        $catId = (int) self::factory()->category->create(['name' => 'Arena MG1 Trio Category']);
        $this->createPostsInCategory($catId, 3);

        $html = Renderer::render('modern-grid-1', ['category' => (string) $catId, 'count' => '4']);

        $this->assertSame(3, substr_count($html, 'listing-item-hero'));
        $this->assertStringContainsString('mg1-row-bottom', $html);
        // Exactly one tile inside the bottom row (no 4th, no empty sibling markup).
        $this->assertSame(1, substr_count($html, 'mg1-tile'));
    }
}
