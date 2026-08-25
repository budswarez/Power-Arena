<?php
declare(strict_types=1);

use Arena\Listing\Renderer;

class RendererTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        // Renderer::$shown is a request-lifetime static; reset it so each
        // test's `disable_duplicate` state starts independent of whatever
        // ran before it in this process.
        Renderer::resetShown();
        // Mesmo motivo: o trinco de 'acima da dobra' tambem e um static de
        // vida-de-requisicao (Arena\Media::claimAboveTheFoldBlock()).
        \Arena\Media::resetAboveTheFoldBlock();
    }

    /** @return int[] */
    private function extractPostIds(string $html): array {
        preg_match_all('/\bpost-(\d+)\b/', $html, $matches);
        return array_map('intval', $matches[1]);
    }

    /**
     * BUG 4 (task-uifix): a thumbnail-less post used to leave an empty,
     * non-clickable box where the thumb would be. Asserts the theme's own
     * default placeholder image (assets/img/placeholder.svg) renders
     * INSIDE a real anchor pointing at the post — never a bare
     * `aria-hidden` div with no link.
     */
    private function assertThumbPlaceholderLinksToPost(string $html, int $postId): void {
        $permalink = get_permalink($postId);
        $this->assertIsString($permalink);

        $this->assertMatchesRegularExpression(
            '#<a[^>]*class="[^"]*thumb-placeholder[^"]*"[^>]*href="' . preg_quote(esc_url($permalink), '#') . '"[^>]*>\s*<img[^>]*src="[^"]*placeholder\.svg[^"]*"#',
            $html,
            'Expected the default placeholder <img> (assets/img/placeholder.svg) wrapped inside a real <a> ' .
                'linking to the thumbnail-less post — never a bare aria-hidden div with no link.'
        );
    }

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

    /**
     * BUG 4 (task-uifix): `grid` (card/featured.php) — the home's "Últimas
     * notícias" layout.
     */
    public function test_render_grid_layout_thumbless_post_renders_placeholder_inside_a_link_to_the_post(): void {
        $postId = $this->factory()->post->create(['post_title' => 'Arena Grid No Thumb', 'post_status' => 'publish']);

        $html = Renderer::render('grid', ['count' => '1']);

        $this->assertThumbPlaceholderLinksToPost($html, $postId);
    }

    /**
     * BUG 4 (task-uifix): `modern-grid` (card/hero.php) — the home's hero
     * mosaic.
     */
    public function test_render_modern_grid_thumbless_post_renders_placeholder_inside_a_link_to_the_post(): void {
        $postId = $this->factory()->post->create(['post_title' => 'Arena Hero No Thumb', 'post_status' => 'publish']);

        $html = Renderer::render('modern-grid', ['count' => '1']);

        $this->assertThumbPlaceholderLinksToPost($html, $postId);
    }

    /**
     * BUG 4 (task-uifix): `blog` (card/list.php, 'blog' variant).
     */
    public function test_render_blog_layout_thumbless_post_renders_placeholder_inside_a_link_to_the_post(): void {
        $postId = $this->factory()->post->create(['post_title' => 'Arena Blog No Thumb', 'post_status' => 'publish']);

        $html = Renderer::render('blog', ['count' => '1']);

        $this->assertThumbPlaceholderLinksToPost($html, $postId);
    }

    /**
     * BUG 4 (task-uifix): `mix` row-2 (card/text.php) — previously the
     * ONLY card partial that omitted its `.featured` block entirely (no
     * placeholder at all, real or otherwise) when a post had no thumbnail.
     */
    public function test_render_mix_layout_row_two_thumbless_post_renders_placeholder_inside_a_link_to_the_post(): void {
        $this->factory()->post->create(['post_title' => 'Arena Mix Row1', 'post_status' => 'publish']);
        $rowTwoId = $this->factory()->post->create(['post_title' => 'Arena Mix Row2 No Thumb', 'post_status' => 'publish']);

        $html = Renderer::render('mix', ['count' => '2']);

        $this->assertThumbPlaceholderLinksToPost($html, $rowTwoId);
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

    public function test_render_honors_short_hex_heading_color(): void {
        $this->factory()->post->create(['post_title' => 'Arena Short Hex Post', 'post_status' => 'publish']);

        $html = Renderer::render('grid', [
            'count'         => '1',
            'title'         => 'Destaques',
            'heading_color' => '#f00',
        ]);

        $this->assertStringContainsString('color:#f00', $html);
    }

    /**
     * task-final-ui item 1 (owner-reported): the home's `[bs-blog-listing-1]`
     * ("blog" layout) block renders with production content passing
     * `title=""` — every neighbouring block on the row has a heading, this
     * one alone didn't. Not a parity fix (the reference site also renders
     * no heading there) — a deliberate default the owner asked for. An
     * empty title (and no `hide_title`) must now render a default heading.
     */
    public function test_render_blog_layout_with_empty_title_renders_default_heading(): void {
        $this->factory()->post->create(['post_title' => 'Arena Blog Default Heading Post', 'post_status' => 'publish']);

        $html = Renderer::render('blog', ['count' => '1', 'title' => '']);

        $this->assertStringContainsString('section-heading', $html);
        $this->assertStringContainsString('Postagens recentes', $html);
    }

    /**
     * task-final-ui item 1: an explicit non-empty `title` must still win
     * over the new default — the default only fills in when the block
     * itself supplies nothing.
     */
    public function test_render_blog_layout_with_explicit_title_uses_that_title_not_the_default(): void {
        $this->factory()->post->create(['post_title' => 'Arena Blog Explicit Heading Post', 'post_status' => 'publish']);

        $html = Renderer::render('blog', ['count' => '1', 'title' => 'Minhas Postagens']);

        $this->assertStringContainsString('Minhas Postagens', $html);
        $this->assertStringNotContainsString('Postagens recentes', $html);
    }

    /**
     * task-final-ui item 1: `hide_title="1"` must still suppress the
     * heading entirely, even though `blog` now has a non-empty default —
     * the explicit suppression flag is checked before the default ever
     * gets a chance to apply.
     */
    public function test_render_blog_layout_with_hide_title_suppresses_default_heading(): void {
        $this->factory()->post->create(['post_title' => 'Arena Blog Hidden Default Post', 'post_status' => 'publish']);

        $html = Renderer::render('blog', ['count' => '1', 'title' => '', 'hide_title' => '1']);

        $this->assertStringNotContainsString('section-heading', $html);
        $this->assertStringNotContainsString('Postagens recentes', $html);
    }

    /**
     * task-final-ui item 1: other layouts (e.g. `grid`) must keep their
     * ORIGINAL "empty title -> no heading" behaviour — only `blog` was
     * reported as missing a heading, so the default must not leak into the
     * other 3 layouts.
     */
    public function test_render_grid_layout_with_empty_title_still_renders_no_heading(): void {
        $this->factory()->post->create(['post_title' => 'Arena Grid No Default Heading Post', 'post_status' => 'publish']);

        $html = Renderer::render('grid', ['count' => '1', 'title' => '']);

        $this->assertStringNotContainsString('section-heading', $html);
        $this->assertStringNotContainsString('Postagens recentes', $html);
    }

    /**
     * task-final-ui item 1: the default wording is filterable
     * (`arena_default_listing_title`) so the owner can reword it without a
     * PHP change.
     */
    public function test_render_blog_layout_default_heading_is_filterable(): void {
        $this->factory()->post->create(['post_title' => 'Arena Blog Filtered Heading Post', 'post_status' => 'publish']);

        $filter = static function (string $default, string $layout, array $atts): string {
            return 'Título customizado';
        };
        add_filter('arena_default_listing_title', $filter, 10, 3);

        try {
            $html = Renderer::render('blog', ['count' => '1', 'title' => '']);
        } finally {
            remove_filter('arena_default_listing_title', $filter, 10);
        }

        $this->assertStringContainsString('Título customizado', $html);
        $this->assertStringNotContainsString('Postagens recentes', $html);
    }

    /**
     * task-final-ui item 1: the default heading must use the same
     * per-section colour mechanism as every other block — i.e. when no
     * `heading_color` attribute is supplied, no inline `style` is added at
     * all, leaving `.section-heading.sh-t6.sh-s6`'s own CSS default
     * (`color: var(--arena-accent)`) in charge, exactly like an explicit
     * title with no colour would.
     */
    public function test_render_blog_layout_default_heading_has_no_inline_color_by_default(): void {
        $this->factory()->post->create(['post_title' => 'Arena Blog Default Color Post', 'post_status' => 'publish']);

        $html = Renderer::render('blog', ['count' => '1', 'title' => '']);

        $this->assertStringContainsString('<div class="section-heading sh-t6 sh-s6">', $html);
        $this->assertStringNotContainsString('style=', $html);
    }

    /**
     * Minor finding #9 (whole-branch review): `hide_title="1"` used to be
     * silently dropped by `shortcode_atts()` (not in the defaults list at
     * all) — a block with a non-empty `title` still rendered the heading
     * even when the editor had explicitly asked to hide it.
     */
    public function test_render_hide_title_suppresses_heading_even_with_a_title_set(): void {
        $this->factory()->post->create(['post_title' => 'Arena Hidden Heading Post', 'post_status' => 'publish']);

        $html = Renderer::render('grid', [
            'count'      => '1',
            'title'      => 'Destaques',
            'hide_title' => '1',
        ]);

        $this->assertStringNotContainsString('Destaques', $html);
        $this->assertStringNotContainsString('section-heading', $html);
    }

    /**
     * Minor finding #9: `bs-show-desktop`/`bs-show-tablet`/`bs-show-phone`
     * used to be silently dropped by `shortcode_atts()` — the block always
     * rendered visible on every breakpoint no matter what an editor set
     * them to. Now they add a cheap `.bs-listing-hide-*` CSS class to the
     * block's own wrapper (main.css hides it at the matching breakpoint).
     */
    public function test_render_bs_show_flags_add_hide_classes_to_wrapper(): void {
        $this->factory()->post->create(['post_status' => 'publish']);

        $html = Renderer::render('grid', [
            'count'           => '1',
            'bs-show-desktop' => '0',
            'bs-show-tablet'  => '1',
            'bs-show-phone'   => '0',
        ]);

        $this->assertStringContainsString('bs-listing-hide-desktop', $html);
        $this->assertStringContainsString('bs-listing-hide-phone', $html);
        $this->assertStringNotContainsString('bs-listing-hide-tablet', $html);
    }

    public function test_render_bs_show_flags_default_to_all_visible(): void {
        $this->factory()->post->create(['post_status' => 'publish']);

        $html = Renderer::render('grid', ['count' => '1']);

        $this->assertStringNotContainsString('bs-listing-hide-', $html);
    }

    /**
     * FIX B.4: `heading_color` reaches `style="color:..."` with only
     * `esc_attr()` guarding it — that stops it breaking OUT of the
     * attribute, but not from injecting extra CSS declarations inside it.
     * The ACF/VC field behind it is a colorpicker, so hex is the only
     * legitimate contract; anything else must be dropped, not echoed.
     */
    public function test_render_drops_invalid_heading_color_instead_of_injecting_css(): void {
        $this->factory()->post->create(['post_title' => 'Arena Bad Color Post', 'post_status' => 'publish']);

        $html = Renderer::render('grid', [
            'count'         => '1',
            'title'         => 'Destaques',
            'heading_color' => 'red;position:fixed;inset:0;background:#000',
        ]);

        $this->assertStringContainsString('Destaques', $html);
        $this->assertStringNotContainsString('position:fixed', $html);
        $this->assertStringNotContainsString('style=', $html);
    }

    /**
     * FIX B.1: `count="-1"` used to feed `posts_per_page => -1` straight
     * into WP_Query, which treats that as "no limit" — a free VcMap
     * textfield means an editor typo like this is one keystroke away.
     * Prove the clamp: only COUNT_MAX (see Query.php) posts render, not
     * every published post.
     */
    public function test_render_clamps_negative_count_instead_of_returning_every_post(): void {
        for ($i = 0; $i < 5; $i++) {
            $this->factory()->post->create(['post_title' => "Arena Clamp Post $i", 'post_status' => 'publish']);
        }

        $html = Renderer::render('grid', ['count' => '-1']);

        // Clamped to the minimum (1): exactly one card renders, not all 5.
        $this->assertSame(1, substr_count($html, 'class="title"'));
    }

    /**
     * FIX B.2: a comma-separated `category` list (Publisher supports
     * `category="14236,17458"`) used to collapse to just the first ID via
     * `(int) $category`. Both categories' posts must appear.
     */
    public function test_render_comma_separated_category_returns_posts_from_both_categories(): void {
        $catA = wp_insert_term('Arena Cat A ' . time(), 'category');
        $catB = wp_insert_term('Arena Cat B ' . (time() + 1), 'category');
        $this->assertIsArray($catA);
        $this->assertIsArray($catB);
        $catAId = (int) $catA['term_id'];
        $catBId = (int) $catB['term_id'];

        $postA = $this->factory()->post->create(['post_title' => 'Arena Multi Cat Post A', 'post_status' => 'publish']);
        $postB = $this->factory()->post->create(['post_title' => 'Arena Multi Cat Post B', 'post_status' => 'publish']);
        wp_set_post_categories($postA, [$catAId]);
        wp_set_post_categories($postB, [$catBId]);

        $html = Renderer::render('grid', ['count' => '10', 'category' => $catAId . ',' . $catBId]);

        $this->assertStringContainsString('Arena Multi Cat Post A', $html);
        $this->assertStringContainsString('Arena Multi Cat Post B', $html);
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

    /**
     * Layout `archive` ("blog-5"): reused by category/tag/author archives
     * and by search results (Task 3 brief). One post is deliberately left
     * without a thumbnail to prove the thumbless degrade never leaves an
     * empty `<img src="">` box behind.
     *
     * @return array{0: int, 1: int, 2: int} [$oldestNoThumb, $middleWithThumb, $newestWithThumb]
     */
    private function createArchiveFixturePosts(): array {
        $now = time();

        $postA = $this->factory()->post->create([
            'post_title'  => 'Arena Archive Post Alpha',
            'post_status' => 'publish',
            'post_date'   => gmdate('Y-m-d H:i:s', $now - 300),
        ]);

        $postB = $this->factory()->post->create([
            'post_title'  => 'Arena Archive Post Beta',
            'post_status' => 'publish',
            'post_date'   => gmdate('Y-m-d H:i:s', $now - 200),
        ]);
        $attachmentB = $this->factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg', $postB);
        set_post_thumbnail($postB, $attachmentB);

        $postC = $this->factory()->post->create([
            'post_title'  => 'Arena Archive Post Gamma',
            'post_status' => 'publish',
            'post_date'   => gmdate('Y-m-d H:i:s', $now - 100),
        ]);
        $attachmentC = $this->factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg', $postC);
        set_post_thumbnail($postC, $attachmentC);

        return [$postA, $postB, $postC];
    }

    public function test_render_archive_layout_shows_blog5_cards_and_degrades_thumbless_post(): void {
        [$alphaId] = $this->createArchiveFixturePosts();

        $html = Renderer::render('archive', ['count' => '3']);

        $this->assertStringContainsString('listing-blog-5', $html);
        $this->assertStringContainsString('listing-item-blog-5', $html);
        foreach (['Arena Archive Post Alpha', 'Arena Archive Post Beta', 'Arena Archive Post Gamma'] as $title) {
            $this->assertStringContainsString(esc_html($title), $html);
        }
        $this->assertStringContainsString('<svg class="icon-comment"', $html);
        // Alpha has no thumbnail: the card must degrade to text-only, never
        // an empty <img src="">.
        $this->assertStringNotContainsString('src=""', $html);
        // BUG 4 (task-uifix): Alpha's missing thumbnail must render the
        // theme's own default placeholder image, wrapped in a link to Alpha
        // itself — never an empty, non-clickable box.
        $this->assertThumbPlaceholderLinksToPost($html, $alphaId);
    }

    public function test_archive_listing_uses_seo_description_before_excerpt(): void {
        $postId = $this->factory()->post->create([
            'post_title'   => 'Arena SEO Listing Post',
            'post_excerpt' => 'Resumo longo do excerpt que não deve aparecer.',
            'post_status'  => 'publish',
        ]);
        update_post_meta($postId, 'rank_math_description', 'Descrição SEO curta para o cartão.');

        $html = Renderer::render('archive', ['count' => '1']);

        $this->assertStringContainsString('Descrição SEO curta para o cartão.', $html);
        $this->assertStringNotContainsString('Resumo longo do excerpt que não deve aparecer.', $html);
    }

    public function test_render_archive_layout_marks_only_the_first_item_for_lcp(): void {
        $this->createArchiveFixturePosts();

        // Query order is date DESC by default, so Gamma (newest) renders
        // first, then Beta, then Alpha (which has no thumbnail at all).
        $html = Renderer::render('archive', ['count' => '3']);

        $this->assertSame(1, substr_count($html, 'fetchpriority="high"'));

        $fetchPos = strpos($html, 'fetchpriority="high"');
        // NOT strpos($html, 'title text') here: the thumb's own `alt`
        // attribute already carries the post title and is written before
        // `fetchpriority` in attribute order (same order as
        // card/featured.php and card/hero.php), so comparing against the
        // raw title string would just match inside that `alt="..."` and
        // always come out "before". The `<h2 class="title">` heading is
        // the actual title element and is unambiguous.
        $firstTitleHeadingPos = strpos($html, '<h2 class="title">');

        $this->assertNotFalse($fetchPos);
        $this->assertNotFalse($firstTitleHeadingPos);
        // fetchpriority sits inside the first item's own thumb markup,
        // which precedes that item's <h2 class="title"> heading in the DOM.
        $this->assertLessThan($firstTitleHeadingPos, $fetchPos);

        // And that first item really is the newest post (Gamma): its title
        // is already visible (inside the thumb's alt text) before the very
        // first <h2> heading is reached.
        $this->assertStringContainsString(
            'Arena Archive Post Gamma',
            substr($html, 0, $firstTitleHeadingPos)
        );
    }

    /**
     * FIX 2 (CRITICAL): reproduces the real home — a hero block with
     * `disable_duplicate="1"` rendered first, then a second block ALSO
     * with the flag on. The second block must not repeat any post the
     * first one already showed.
     */
    public function test_disable_duplicate_on_excludes_posts_shown_by_an_earlier_block(): void {
        for ($i = 0; $i < 6; $i++) {
            $this->factory()->post->create([
                'post_title'  => "Arena Dedup Post $i",
                'post_status' => 'publish',
            ]);
        }

        $first = Renderer::render('grid', ['count' => '3', 'disable_duplicate' => '1']);
        $second = Renderer::render('grid', ['count' => '6', 'disable_duplicate' => '1']);

        $firstIds = $this->extractPostIds($first);
        $secondIds = $this->extractPostIds($second);

        $this->assertNotEmpty($firstIds);
        $this->assertNotEmpty($secondIds);
        $this->assertSame([], array_intersect($firstIds, $secondIds));
    }

    /**
     * With the flag OFF on the second block, overlap with an earlier
     * block's posts must be allowed (Publisher default behaviour when a
     * block doesn't opt in to dedup).
     */
    public function test_disable_duplicate_off_allows_overlap_with_earlier_block(): void {
        for ($i = 0; $i < 3; $i++) {
            $this->factory()->post->create([
                'post_title'  => "Arena Overlap Post $i",
                'post_status' => 'publish',
            ]);
        }

        $first = Renderer::render('grid', ['count' => '3', 'disable_duplicate' => '1']);
        $second = Renderer::render('grid', ['count' => '3', 'disable_duplicate' => '0']);

        $firstIds = $this->extractPostIds($first);
        $secondIds = $this->extractPostIds($second);

        $this->assertNotEmpty(array_intersect($firstIds, $secondIds));
    }

    /**
     * Every block contributes its own rendered IDs to the shared "shown"
     * list regardless of its OWN `disable_duplicate` value — only whether
     * a block's query itself excludes from that list depends on its own
     * flag. A third block with the flag on must therefore avoid posts
     * shown by the second block even though the second had the flag off.
     */
    public function test_blocks_without_the_flag_still_contribute_ids_for_later_blocks(): void {
        for ($i = 0; $i < 4; $i++) {
            $this->factory()->post->create([
                'post_title'  => "Arena Contribute Post $i",
                'post_status' => 'publish',
            ]);
        }

        Renderer::render('grid', ['count' => '2', 'disable_duplicate' => '0']);
        $third = Renderer::render('grid', ['count' => '4', 'disable_duplicate' => '1']);

        // The 2 posts shown by the very first (no-flag) render must not
        // reappear in the third (flag-on) render, even though the second
        // render never had the flag on either.
        $this->assertLessThanOrEqual(2, count($this->extractPostIds($third)));
    }

    public function test_reset_shown_clears_state_between_independent_renders(): void {
        $this->factory()->post->create(['post_title' => 'Arena Reset Post', 'post_status' => 'publish']);

        $first = Renderer::render('grid', ['count' => '1', 'disable_duplicate' => '1']);
        Renderer::resetShown();
        $second = Renderer::render('grid', ['count' => '1', 'disable_duplicate' => '1']);

        $this->assertSame($this->extractPostIds($first), $this->extractPostIds($second));
    }
}
