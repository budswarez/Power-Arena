<?php
declare(strict_types=1);

/**
 * 404.php (Fatia 2B Task 5) — replaces the near-empty index.php fallback a
 * missing URL used to fall through to. Same full-template render strategy
 * as tests/ArchiveTemplateTest.php/SingleTemplateTest.php.
 */
class NotFoundTemplateTest extends WP_UnitTestCase {
    public function set_up(): void {
        parent::set_up();
        // Pretty permalinks: a plain-permalink structure (this test suite's
        // default) routes an unknown PATH like `/does-not-exist-…` through
        // rewrite rules differently than WordPress does on a live site —
        // `is_404()` needs a real rewrite structure to reliably recognize an
        // arbitrary path as "matches nothing" rather than falling through
        // to the default/home query.
        $this->set_permalink_structure('/%postname%/');
    }

    private function renderNotFound(): string {
        $this->go_to(home_url('/does-not-exist-' . uniqid()));
        $this->assertTrue(is_404(), 'go_to() must land on a 404 query for this test to be meaningful.');

        ob_start();
        load_template(get_template_directory() . '/404.php', false);
        return (string) ob_get_clean();
    }

    public function test_missing_url_renders_not_found_message_and_search_form(): void {
        $html = $this->renderNotFound();

        $this->assertNotSame('', $html);
        $this->assertStringNotContainsString('Fatal error', $html);
        // Exactly one H1 on the page.
        $this->assertSame(1, preg_match_all('/<h1[ >]/', $html));
        $this->assertStringContainsString('não encontrada', $html);
        $this->assertStringContainsString('role="search"', $html);
    }

    public function test_404_shows_supplementary_recent_posts_listing(): void {
        self::factory()->post->create([
            'post_title'  => 'Arena 404 Recent Post',
            'post_status' => 'publish',
        ]);

        $html = $this->renderNotFound();

        $this->assertStringContainsString('Arena 404 Recent Post', $html);
        $this->assertStringContainsString('listing-blog-5', $html);
    }
}
