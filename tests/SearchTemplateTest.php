<?php
declare(strict_types=1);

/**
 * search.php + searchform.php (Fatia 2B Task 5). Same full-template render
 * strategy as tests/ArchiveTemplateTest.php/SingleTemplateTest.php:
 * go_to() a real search URL, load_template() the whole file end to end,
 * and assert on the rendered HTML — the strongest signal that nothing
 * fatals and that the paginated listing runs off the GLOBAL $wp_query
 * (pagination-safe), not a second Renderer::render() query, exactly like
 * archive.php's own main listing (see Task 4's report for why that
 * distinction matters).
 */
class SearchTemplateTest extends WP_UnitTestCase {
    private function renderSearch(string $url): string {
        $this->go_to($url);
        $this->assertTrue(is_search(), 'go_to() must land on a search query for this test to be meaningful.');

        ob_start();
        load_template(get_template_directory() . '/search.php', false);
        return (string) ob_get_clean();
    }

    /**
     * Slices out just the MAIN paginated listing (`listing-blog-5` wrapper
     * through the start of the sidebar column) — the default sidebar's own
     * "Recent Posts" widget lists every post sitewide regardless of which
     * search-results page is showing, so it is not a meaningful signal for
     * "did pagination actually swap which posts are showing" (same issue,
     * same fix, as tests/ArchiveTemplateTest.php's own helper).
     */
    private function extractMainListing(string $html): string {
        $start = strpos($html, 'listing-blog-5');
        $end = strpos($html, 'sidebar-column');
        if ($start === false || $end === false || $end <= $start) {
            return $html;
        }

        return substr($html, $start, $end - $start);
    }

    public function test_search_with_results_renders_header_listing_and_titles(): void {
        $token = 'arenasearchtoken' . uniqid();
        $postIds = [];
        for ($i = 0; $i < 3; $i++) {
            $postIds[] = self::factory()->post->create([
                'post_title'  => 'Post ' . $token . ' Number ' . $i,
                'post_status' => 'publish',
            ]);
        }

        $html = $this->renderSearch(home_url('/?s=' . $token));

        $this->assertNotSame('', $html);
        $this->assertStringNotContainsString('Fatal error', $html);
        // Exactly one H1 on the page.
        $this->assertSame(1, preg_match_all('/<h1[ >]/', $html));
        // The searched term itself must appear (inside the search-header's H1).
        $this->assertStringContainsString($token, $html);
        $this->assertStringContainsString('listing-blog-5', $html);
        foreach ($postIds as $index => $postId) {
            $this->assertStringContainsString('Post ' . $token . ' Number ' . $index, $html);
        }

        unset($postIds);
    }

    public function test_second_page_of_search_results_shows_different_posts(): void {
        $token = 'arenapagedsearch' . uniqid();
        update_option('posts_per_page', 2);
        $titles = [];
        for ($i = 0; $i < 4; $i++) {
            $title = 'Paged ' . $token . ' ' . $i;
            $titles[] = $title;
            self::factory()->post->create([
                'post_title'  => $title,
                'post_status' => 'publish',
                'post_date'   => gmdate('Y-m-d H:i:s', time() - $i * 60),
            ]);
        }

        $page1Listing = $this->extractMainListing($this->renderSearch(home_url('/?s=' . $token)));
        $page2Listing = $this->extractMainListing($this->renderSearch(add_query_arg(['s' => $token, 'paged' => 2], home_url('/'))));

        $this->assertStringContainsString($titles[0], $page1Listing);
        $this->assertStringContainsString($titles[1], $page1Listing);
        $this->assertStringNotContainsString($titles[2], $page1Listing);

        $this->assertStringContainsString($titles[2], $page2Listing);
        $this->assertStringContainsString($titles[3], $page2Listing);
        $this->assertStringNotContainsString($titles[0], $page2Listing);
    }

    public function test_search_with_no_results_renders_empty_state_and_recent_posts(): void {
        // A post that must NOT match the search term below — it only exists
        // so the empty-state's supplementary "recent posts" block has
        // something to show.
        self::factory()->post->create([
            'post_title'  => 'Arena Recent Fallback Post',
            'post_status' => 'publish',
        ]);

        $html = $this->renderSearch(home_url('/?s=zzzznotfoundzzzz'));

        $this->assertNotSame('', $html);
        $this->assertStringNotContainsString('Fatal error', $html);
        $this->assertSame(1, preg_match_all('/<h1[ >]/', $html));
        $this->assertStringContainsString('search-empty', $html);
        $this->assertStringContainsString('role="search"', $html);
        // The recent-posts fallback block intentionally reuses the SAME
        // "blog-5" card layout (template-parts/listing/archive.php) that the
        // primary results listing uses, so the mere presence/absence of
        // "listing-blog-5" can't distinguish "empty state with a recent
        // posts fallback" from "results". The unambiguous, explicit signal
        // asserted here instead is that the RECENT post's own title shows up
        // (proving the supplementary block rendered) while there is no
        // pagination control at all — search.php never calls the pagination
        // partial in the empty-state branch, unlike the with-results branch.
        $this->assertStringContainsString('Arena Recent Fallback Post', $html);
        $this->assertStringNotContainsString('arena-pagination', $html);
    }
}
