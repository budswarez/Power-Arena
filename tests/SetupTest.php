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

    /**
     * GAP C: templates call __()/esc_html_e() with the `arena` text domain
     * throughout, but nothing ever loaded a translation file for it — the
     * hook is the genuinely-assertable part (Setup::register() runs once,
     * at theme boot, so re-invoking load_theme_textdomain() directly here
     * wouldn't prove the theme actually wires it up on 'after_setup_theme'
     * the way production does).
     */
    public function test_registers_textdomain_loader_on_after_setup_theme(): void {
        $this->assertNotFalse(
            has_action('after_setup_theme', [Setup::class, 'loadTextdomain']),
            'Setup::register() must hook loadTextdomain() onto after_setup_theme.'
        );
    }

    /**
     * Arena\Setup::loadTextdomain() must point load_theme_textdomain() at
     * the theme's own languages/ dir. WordPress 6.1+ resolves textdomain
     * paths lazily via WP_Textdomain_Registry rather than eagerly reading
     * a .mo file when load_theme_textdomain() itself runs — so the
     * genuinely-assertable outcome is the path registered with that
     * registry (WP_Textdomain_Registry::set_custom_path(), called
     * internally by load_theme_textdomain()), not an eager file read.
     */
    public function test_load_textdomain_registers_the_theme_languages_directory(): void {
        global $wp_textdomain_registry;

        $result = Setup::loadTextdomain();
        $this->assertTrue($result);

        $path = $wp_textdomain_registry->get('arena', get_locale());

        $this->assertNotFalse($path, 'The "arena" domain must resolve to a registered languages directory.');
        $this->assertStringContainsString(
            str_replace('\\', '/', get_template_directory() . '/languages'),
            str_replace('\\', '/', (string) $path)
        );
    }
}
