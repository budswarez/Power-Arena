<?php
declare(strict_types=1);

use Arena\Setup;

class SetupTest extends WP_UnitTestCase {
    /**
     * Re-firing 'after_setup_theme' inside a test also re-runs
     * Setup::themeSupports(), which calls add_theme_support('title-tag').
     * WordPress core flags that call as incorrect usage once 'wp_loaded' has
     * already fired — which it has by the time any test body runs. This is
     * an artifact of the test manually re-triggering the hook, not a real
     * bug in the theme: in normal execution 'after_setup_theme' fires
     * exactly once, well before 'wp_loaded'. We tell WP_UnitTestCase to
     * expect it so it doesn't fail the test on an unrelated notice.
     */
    public function test_registers_primary_menu(): void {
        $this->setExpectedIncorrectUsage("add_theme_support( 'title-tag' )");
        do_action('after_setup_theme');
        $this->assertArrayHasKey('arena_primary', get_registered_nav_menus());
    }

    public function test_registers_card_image_size(): void {
        $this->setExpectedIncorrectUsage("add_theme_support( 'title-tag' )");
        do_action('after_setup_theme');
        $this->assertTrue(has_image_size('arena-card'));
    }

    /**
     * Publisher (the legacy theme) assigns existing menus to the location
     * slugs `main-menu`, `top-menu` and `resp-menu`. Registering the same
     * slugs here lets those assignments carry over untouched on migration.
     * `arena_primary` is kept as an extra/alias location.
     */
    public function test_registers_publisher_compatible_menu_locations(): void {
        $this->setExpectedIncorrectUsage("add_theme_support( 'title-tag' )");
        do_action('after_setup_theme');
        $locations = get_registered_nav_menus();
        $this->assertArrayHasKey('main-menu', $locations);
        $this->assertArrayHasKey('top-menu', $locations);
        $this->assertArrayHasKey('resp-menu', $locations);
        $this->assertArrayHasKey('arena_primary', $locations);
    }

    /** Closes the Fatia-1 gap: the `arena-primary` sidebar must be registered. */
    public function test_registers_primary_sidebar(): void {
        do_action('widgets_init');
        $this->assertTrue(is_registered_sidebar('arena-primary'));
    }

    /**
     * On single/archive/search the reference's shell body classes must be
     * present so the 2-column-grid CSS hooks (main.css) match.
     */
    public function test_shell_body_classes_present_on_single(): void {
        $postId = $this->factory()->post->create(['post_status' => 'publish']);
        $this->go_to(get_permalink($postId));

        $classes = Setup::shellBodyClasses([]);

        $this->assertContains('page-layout-2-col', $classes);
        $this->assertContains('page-layout-2-col-right', $classes);
        $this->assertContains('active-sticky-sidebar', $classes);
    }

    /**
     * GAP 3: the reference's ordinary PAGES use the same 2-column
     * right-sidebar shell as posts — page.php now opts into it.
     */
    public function test_shell_body_classes_present_on_ordinary_page(): void {
        $pageId = $this->factory()->post->create(['post_type' => 'page', 'post_status' => 'publish']);
        $this->go_to(get_permalink($pageId));

        $classes = Setup::shellBodyClasses([]);

        $this->assertContains('page-layout-2-col', $classes);
        $this->assertContains('page-layout-2-col-right', $classes);
        $this->assertContains('active-sticky-sidebar', $classes);
    }

    /** The home page must not gain the shell's 2-column classes. */
    public function test_shell_body_classes_absent_on_front_page(): void {
        $pageId = $this->factory()->post->create(['post_type' => 'page', 'post_status' => 'publish']);
        update_option('show_on_front', 'page');
        update_option('page_on_front', $pageId);
        $this->go_to(get_permalink($pageId));

        $classes = Setup::shellBodyClasses(['existing-class']);

        $this->assertSame(['existing-class'], $classes);
    }
}
