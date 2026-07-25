<?php
declare(strict_types=1);

/**
 * archive.php + template-parts/archive-header.php — the single template
 * that serves category, tag, author and date archives via WordPress' own
 * template hierarchy (Fatia 2B Task 4). Renders the FULL template
 * (get_header() through get_footer()) via load_template() against a real
 * go_to()'d archive URL, same strategy as tests/SingleTemplateTest.php —
 * the strongest signal that nothing fatals end to end and that the main
 * query (not a second WP_Query) drives the paginated listing.
 */
class ArchiveTemplateTest extends WP_UnitTestCase {
    private function renderArchive(string $url): string {
        $this->go_to($url);

        ob_start();
        load_template(get_template_directory() . '/archive.php', false);
        return (string) ob_get_clean();
    }

    /**
     * Slices out just the MAIN paginated listing (`listing-blog-5` wrapper
     * through the start of the sidebar column) — the "featured" modern-grid
     * block above it deliberately shows its own independent top-N pick on
     * every page (see archive.php's docblock), and the default sidebar's
     * "Recent Posts" widget lists every post sitewide regardless of which
     * archive page is showing, so neither block is a meaningful signal for
     * "did pagination actually swap which posts are showing" — only the
     * main listing itself is.
     */
    private function extractMainListing(string $html): string {
        $start = strpos($html, 'listing-blog-5');
        $end = strpos($html, 'sidebar-column');
        if ($start === false || $end === false || $end <= $start) {
            return $html;
        }

        return substr($html, $start, $end - $start);
    }

    public function test_category_archive_renders_header_listing_and_post_titles(): void {
        $catId = self::factory()->category->create(['name' => 'Arena Hardware Test']);
        $postIds = [];
        for ($i = 0; $i < 3; $i++) {
            $postIds[] = self::factory()->post->create([
                'post_title'   => 'Arena Archive Post ' . $i,
                'post_status'  => 'publish',
                'post_category' => [$catId],
            ]);
        }

        $this->assertTrue(is_string(get_term_link($catId)));
        $html = $this->renderArchive((string) get_term_link($catId));

        $this->assertNotSame('', $html);
        $this->assertStringNotContainsString('Fatal error', $html);
        // Exactly one H1 on the page.
        $this->assertSame(1, preg_match_all('/<h1[ >]/', $html));
        $this->assertStringContainsString('Arena Hardware Test', $html);
        $this->assertStringContainsString('listing-blog-5', $html);
        foreach ($postIds as $index => $postId) {
            $this->assertStringContainsString('Arena Archive Post ' . $index, $html);
        }
    }

    public function test_category_with_description_renders_it(): void {
        $catId = self::factory()->category->create(['name' => 'Arena Desc Category']);
        wp_update_term($catId, 'category', ['description' => 'Uma descricao de categoria para o teste do arquivo.']);
        self::factory()->post->create(['post_status' => 'publish', 'post_category' => [$catId]]);

        $html = $this->renderArchive((string) get_term_link($catId));

        $this->assertStringContainsString('archive-description', $html);
        $this->assertStringContainsString('Uma descricao de categoria para o teste do arquivo.', $html);
    }

    public function test_category_without_description_has_no_empty_wrapper(): void {
        // WP core's own term factory auto-fills a 'Description %s' sequence
        // when none is given — must override it to '' to actually exercise
        // the "no description" branch.
        $catId = self::factory()->category->create(['name' => 'Arena No Desc Category', 'description' => '']);
        self::factory()->post->create(['post_status' => 'publish', 'post_category' => [$catId]]);

        $html = $this->renderArchive((string) get_term_link($catId));

        $this->assertStringNotContainsString('archive-description', $html);
    }

    public function test_category_with_children_shows_term_chips(): void {
        $parentId = self::factory()->category->create(['name' => 'Arena Parent Category']);
        $childId = self::factory()->category->create(['name' => 'Arena Child Category', 'parent' => $parentId]);
        self::factory()->post->create(['post_status' => 'publish', 'post_category' => [$parentId]]);

        $html = $this->renderArchive((string) get_term_link($parentId));

        $this->assertStringContainsString('with-terms', $html);
        $this->assertStringContainsString('Arena Child Category', $html);

        unset($childId);
    }

    public function test_author_archive_renders_author_name(): void {
        $userId = self::factory()->user->create([
            'display_name' => 'Arena Archive Author',
            'role'         => 'author',
        ]);
        self::factory()->post->create(['post_status' => 'publish', 'post_author' => $userId]);

        $url = get_author_posts_url($userId);
        $this->assertNotSame('', $url);

        $html = $this->renderArchive($url);

        $this->assertNotSame('', $html);
        $this->assertStringNotContainsString('Fatal error', $html);
        $this->assertSame(1, preg_match_all('/<h1[ >]/', $html));
        $this->assertStringContainsString('Arena Archive Author', $html);
    }

    /**
     * Locks the reference's structural order (Task 2B polish): the
     * "featured" modern-grid block renders BEFORE the archive header
     * (`page-heading` H1), not after — archive.php used to render
     * header -> featured -> listing; the reference renders
     * featured -> header -> listing.
     */
    public function test_featured_block_renders_before_archive_header(): void {
        $catId = self::factory()->category->create(['name' => 'Arena Order Category']);
        for ($i = 0; $i < 5; $i++) {
            self::factory()->post->create([
                'post_title'    => 'Arena Order Post ' . $i,
                'post_status'   => 'publish',
                'post_category' => [$catId],
            ]);
        }

        $html = $this->renderArchive((string) get_term_link($catId));

        $featuredPos = strpos($html, 'bs-listing-modern-grid');
        $headingPos = strpos($html, 'page-heading');

        $this->assertNotFalse($featuredPos, 'Featured modern-grid block should render.');
        $this->assertNotFalse($headingPos, 'Archive header <h1> should render.');
        $this->assertLessThan($headingPos, $featuredPos, 'Featured block must render before the archive header.');
    }

    /**
     * Reference shows a small dark "pre-title" label chip above the H1
     * ("Navegando pela Categoria" for category archives) — the exact
     * text/element measured in ref-category.html.
     */
    public function test_category_archive_shows_label_chip(): void {
        $catId = self::factory()->category->create(['name' => 'Arena Chip Category']);
        self::factory()->post->create(['post_status' => 'publish', 'post_category' => [$catId]]);

        $html = $this->renderArchive((string) get_term_link($catId));

        $this->assertStringContainsString('pre-title', $html);
        $this->assertStringContainsString('Navegando pela Categoria', $html);
    }

    /**
     * Reference overlays a category badge at the bottom-left of every
     * listing card's thumbnail (`.term-badges.floated` with a slug-keyed
     * `data-slug`), not just on the featured tiles.
     */
    public function test_blog5_card_has_category_badge_with_data_slug(): void {
        $catId = self::factory()->category->create(['name' => 'Arena Badge Category']);
        $postId = self::factory()->post->create([
            'post_title'    => 'Arena Badge Post',
            'post_status'   => 'publish',
            'post_category' => [$catId],
        ]);
        set_post_thumbnail($postId, self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg', $postId));

        $slug = get_category($catId)->slug;
        $html = $this->extractMainListing($this->renderArchive((string) get_term_link($catId)));

        $this->assertStringContainsString('data-slug="' . $slug . '"', $html);
    }

    public function test_second_page_of_a_category_archive_shows_different_posts(): void {
        $catId = self::factory()->category->create(['name' => 'Arena Paged Category']);
        update_option('posts_per_page', 2);
        $titles = [];
        for ($i = 0; $i < 4; $i++) {
            $title = 'Arena Paged Post ' . $i;
            $titles[] = $title;
            self::factory()->post->create([
                'post_title'    => $title,
                'post_status'   => 'publish',
                'post_category' => [$catId],
                'post_date'     => gmdate('Y-m-d H:i:s', time() - $i * 60),
            ]);
        }

        $link = (string) get_term_link($catId);
        // Query-string pagination avoids depending on pretty-permalink rewrite
        // rules being flushed/available inside the test environment.
        $page1Listing = $this->extractMainListing($this->renderArchive($link));
        $page2Listing = $this->extractMainListing($this->renderArchive(add_query_arg('paged', 2, $link)));

        $this->assertStringContainsString($titles[0], $page1Listing);
        $this->assertStringContainsString($titles[1], $page1Listing);
        $this->assertStringNotContainsString($titles[2], $page1Listing);

        $this->assertStringContainsString($titles[2], $page2Listing);
        $this->assertStringContainsString($titles[3], $page2Listing);
        $this->assertStringNotContainsString($titles[0], $page2Listing);
    }
}
