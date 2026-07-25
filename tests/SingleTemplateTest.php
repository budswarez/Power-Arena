<?php
declare(strict_types=1);

/**
 * single.php + template-parts/single/*.php — the article page (Fatia 2B
 * Task 2). Renders the FULL template (get_header() through get_footer())
 * via load_template() against a real go_to()'d permalink, exactly like a
 * normal request would, rather than only unit-testing the partials in
 * isolation — this theme's header/footer partials have no hard external
 * dependencies (no manifest file required, Assets::manifest() degrades to
 * an empty array when assets/dist/ doesn't exist yet), so a full render is
 * both practical and the strongest signal that nothing fatals end to end.
 */
class SingleTemplateTest extends WP_UnitTestCase {
    private function renderSingle(int $postId): string {
        $this->go_to(get_permalink($postId));
        $this->assertTrue(is_single(), 'go_to() must land on a singular post query for this test to be meaningful.');

        ob_start();
        load_template(get_template_directory() . '/single.php', false);
        return (string) ob_get_clean();
    }

    public function test_full_article_with_thumbnail_and_tags_renders_expected_shape(): void {
        $postId = $this->factory()->post->create([
            'post_title'   => 'Arena Single Test Post',
            'post_content' => 'Conteudo completo do post de teste da pagina de artigo.',
            'post_status'  => 'publish',
        ]);
        $attachmentId = $this->factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/canola.jpg',
            $postId
        );
        set_post_thumbnail($postId, $attachmentId);
        wp_set_post_tags($postId, ['Arena Single Tag']);

        $html = $this->renderSingle($postId);

        $this->assertNotSame('', $html);
        // Exactly one H1 on the page.
        $this->assertSame(1, preg_match_all('/<h1[ >]/', $html));
        $this->assertStringContainsString('Arena Single Test Post', $html);
        $this->assertStringContainsString('class="entry-content', $html);
        $this->assertStringContainsString('Arena Single Tag', $html);
        $this->assertStringContainsString('post-meta', $html);
        $this->assertStringContainsString('single-featured', $html);
        $this->assertStringNotContainsString('Fatal error', $html);
    }

    public function test_bare_post_without_thumbnail_or_tags_does_not_fatal(): void {
        $postId = $this->factory()->post->create([
            'post_title'   => 'Arena Bare Single Post',
            'post_content' => 'Post sem thumbnail e sem tags.',
            'post_status'  => 'publish',
        ]);

        $html = $this->renderSingle($postId);

        $this->assertNotSame('', $html);
        $this->assertStringContainsString('Arena Bare Single Post', $html);
        $this->assertSame(1, preg_match_all('/<h1[ >]/', $html));
        $this->assertStringNotContainsString('Fatal error', $html);
        $this->assertStringNotContainsString('single-featured', $html);
        $this->assertStringNotContainsString('post-tags', $html);
    }

    public function test_comments_template_does_not_fatal_without_a_comments_plugin(): void {
        $postId = $this->factory()->post->create(['post_status' => 'publish']);

        $html = $this->renderSingle($postId);

        $this->assertStringNotContainsString('Fatal error', $html);
    }

    /**
     * GAP A: the "skip to content" link (header.php) must be the FIRST
     * focusable element in <body> and must target the id actually present
     * on the page's main landmark (`#content`, opened by template-parts/
     * layout/content-open.php).
     *
     * Renders header.php DIRECTLY via load_template(…, false) rather than
     * through single.php's own get_header() call: WP core's get_header()
     * hardcodes locate_template()'s $require_once to true, so header.php
     * only actually executes on the FIRST get_header() call of the whole
     * PHPUnit PROCESS (every test file in the suite shares one process) —
     * every later call is a silent no-op require_once, regardless of which
     * template invoked it. That's a require_once artifact of testing the
     * same in-process PHP repeatedly, not a real production behavior (a
     * real request is its own fresh PHP process), so asserting against it
     * here would make this test's pass/fail depend on unrelated suite
     * ordering. Rendering header.php directly (like every other full-
     * template test in this suite renders ITS OWN top-level file via
     * load_template(..., false)) sidesteps that artifact entirely.
     */
    public function test_skip_link_is_first_focusable_element_and_targets_a_real_landmark(): void {
        $postId = $this->factory()->post->create(['post_status' => 'publish']);
        $this->go_to(get_permalink($postId));

        ob_start();
        load_template(get_template_directory() . '/header.php', false);
        $header = (string) ob_get_clean();

        // The content landmark itself lives in a different partial
        // (template-parts/layout/content-open.php, shared by every
        // template) — render it too so the assertion that the skip link's
        // target actually exists on the page isn't just taken on faith.
        ob_start();
        get_template_part('template-parts/layout/content-open', null, ['layout' => '2col-right']);
        $contentOpen = (string) ob_get_clean();

        $bodyPos = strpos($header, '<body');
        $skipLinkPos = strpos($header, 'class="skip-link"');
        $firstAnchorPos = strpos($header, '<a ');
        $mainLandmarkPos = strpos($contentOpen, 'id="content"');

        $this->assertNotFalse($bodyPos, 'header.php must render the opening <body> tag.');
        $this->assertNotFalse($skipLinkPos, 'header.php must render the skip link.');
        $this->assertNotFalse($mainLandmarkPos, 'The shell must expose the id the skip link targets.');
        $this->assertGreaterThan($bodyPos, $skipLinkPos, 'Skip link must be inside <body>.');
        $this->assertSame($firstAnchorPos, strpos($header, '<a ', $bodyPos), 'Skip link must be the first <a> after <body>.');
        $this->assertStringContainsString('href="#content"', $header);
    }
}
