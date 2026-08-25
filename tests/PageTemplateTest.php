<?php
declare(strict_types=1);

/**
 * page.php — GAP 3: the reference's ordinary PAGES use the same 2-column
 * right-sidebar shell as posts (page-layout-2-col page-layout-2-col-right),
 * not the front page's own full-width boxed wrapper. Renders the FULL
 * template via load_template(), same strategy as SingleTemplateTest.
 */
class PageTemplateTest extends WP_UnitTestCase {
    private function renderTemplate(string $file, int $postId): string {
        $this->go_to(get_permalink($postId));

        ob_start();
        load_template(get_template_directory() . '/' . $file, false);
        return (string) ob_get_clean();
    }

    public function test_ordinary_page_uses_the_2col_shell(): void {
        $pageId = $this->factory()->post->create([
            'post_type'    => 'page',
            'post_title'   => 'Arena Editorial Page',
            'post_content' => 'Conteudo de teste da pagina generica.',
            'post_status'  => 'publish',
        ]);

        $html = $this->renderTemplate('page.php', $pageId);
        $this->assertTrue(is_page(), 'go_to() must land on a singular page query for this test to be meaningful.');

        $this->assertNotSame('', $html);
        $this->assertStringContainsString('Arena Editorial Page', $html);
        $this->assertStringContainsString('content-column', $html);
        $this->assertStringContainsString('sidebar-column', $html);
        $this->assertStringContainsString('layout-right-sidebar', $html);
        $this->assertStringNotContainsString('Fatal error', $html);
    }

    /**
     * A static front page is technically a Page too, so it satisfies
     * is_page() — Arena\Setup::shellBodyClasses() explicitly excludes
     * is_front_page() to keep this case on the full-width layout (see
     * tests/SetupTest.php for the body_class-level guard). This confirms
     * front-page.php itself still renders its own full-width wrapper, not
     * the 2-column shell, when used as the site's front page.
     */
    public function test_static_front_page_still_uses_full_width_layout(): void {
        $pageId = $this->factory()->post->create([
            'post_type'    => 'page',
            'post_title'   => 'Arena Home Page',
            'post_content' => 'Conteudo da home estatica.',
            'post_status'  => 'publish',
        ]);
        update_option('show_on_front', 'page');
        update_option('page_on_front', $pageId);

        $html = $this->renderTemplate('front-page.php', $pageId);

        $this->assertStringContainsString('page-layout-no-sidebar', $html);
        $this->assertStringNotContainsString('content-column', $html);
        $this->assertStringNotContainsString('sidebar-column', $html);
        $this->assertStringNotContainsString('Fatal error', $html);
    }

    /**
     * Minor finding #10 (whole-branch review): the home's body is a
     * WPBakery `[vc_row]` tree of `bs-*` listing blocks whose own card/
     * section headings are all `<h2>` — the page used to render zero
     * `<h1>` anywhere, unlike every other template. Behavioural, not
     * markup-brittle: counts `<h1` tags rather than asserting a specific
     * class/wrapper, so it stays green regardless of exactly how/where the
     * H1 is implemented.
     */
    public function test_front_page_renders_exactly_one_h1(): void {
        $pageId = $this->factory()->post->create([
            'post_type'    => 'page',
            'post_title'   => 'Arena Home Page',
            'post_content' => 'Conteudo da home estatica.',
            'post_status'  => 'publish',
        ]);
        update_option('show_on_front', 'page');
        update_option('page_on_front', $pageId);

        $html = $this->renderTemplate('front-page.php', $pageId);

        $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html));
    }
}
