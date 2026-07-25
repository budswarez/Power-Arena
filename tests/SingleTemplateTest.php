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
}
