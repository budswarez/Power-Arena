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
        $this->assertArrayHasKey('main-menu', get_registered_nav_menus());
    }

    /**
     * Regressão: um local extra `arena_primary` rotulado "Menu Principal"
     * ficou registrado sem que nenhum template o renderizasse. No Customizer
     * ele aparecia com o nome mais óbvio, então atribuir o menu ali não
     * surtia efeito — o cabeçalho sempre renderizou `main-menu`. O invariante
     * que vale testar é este: TODO local registrado tem de ser realmente
     * renderizado por algum template do tema.
     */
    public function test_every_registered_menu_location_is_rendered_by_a_template(): void {
        $this->setExpectedIncorrectUsage("add_theme_support( 'title-tag' )");
        do_action('after_setup_theme');

        /*
         * Varre TODOS os templates, não só header.php/footer.php: o topbar
         * mora em template-parts/header/topbar.php, e o rodapé resolve seu
         * local através de Setup::footerMenuLocation() (por isso o método
         * entra no palheiro via reflexão).
         */
        $dir  = get_template_directory();
        $hay  = '';
        $files = array_merge(
            glob($dir . '/*.php') ?: [],
            glob($dir . '/template-parts/*.php') ?: [],
            glob($dir . '/template-parts/*/*.php') ?: []
        );
        foreach ($files as $file) {
            $hay .= (string) file_get_contents($file);
        }

        $resolver = new \ReflectionMethod(Setup::class, 'footerMenuLocation');
        $source   = file($resolver->getFileName()) ?: [];
        $hay .= implode('', array_slice(
            $source,
            $resolver->getStartLine() - 1,
            $resolver->getEndLine() - $resolver->getStartLine() + 1
        ));

        foreach (array_keys(get_registered_nav_menus()) as $location) {
            $this->assertStringContainsString(
                $location,
                $hay,
                "O local de menu '{$location}' está registrado mas nenhum template o renderiza."
            );
        }
    }

    public function test_registers_card_image_size(): void {
        $this->setExpectedIncorrectUsage("add_theme_support( 'title-tag' )");
        do_action('after_setup_theme');
        $this->assertTrue(has_image_size('arena-card'));
    }

    /**
     * task-native-settings: the theme's only configuration UI used to be an
     * ACF options page, unusable on a site without ACF installed. Native
     * `custom-logo` support (Aparência → Personalizar → Identidade do site)
     * needs zero plugins.
     */
    public function test_registers_native_custom_logo_support(): void {
        $this->setExpectedIncorrectUsage("add_theme_support( 'title-tag' )");
        do_action('after_setup_theme');
        $this->assertTrue(current_theme_supports('custom-logo'));
    }

    public function test_custom_logo_support_dimensions_match_the_header(): void {
        $this->setExpectedIncorrectUsage("add_theme_support( 'title-tag' )");
        do_action('after_setup_theme');

        $support = get_theme_support('custom-logo');
        $args = $support[0];

        $this->assertSame(140, $args['height']);
        $this->assertSame(640, $args['width']);
        $this->assertTrue($args['flex-height']);
        $this->assertTrue($args['flex-width']);
    }

    /**
     * Publisher (the legacy theme) assigns existing menus to the location
     * slugs `main-menu`, `top-menu` and `resp-menu`. Registering the same
     * slugs here lets those assignments carry over untouched on migration.
     */
    public function test_registers_publisher_compatible_menu_locations(): void {
        $this->setExpectedIncorrectUsage("add_theme_support( 'title-tag' )");
        do_action('after_setup_theme');
        $locations = get_registered_nav_menus();
        $this->assertArrayHasKey('main-menu', $locations);
        $this->assertArrayHasKey('top-menu', $locations);
        $this->assertArrayHasKey('resp-menu', $locations);
        $this->assertArrayNotHasKey('arena_primary', $locations);
    }

    /**
     * task-native-settings, migration gap #6: Publisher also registers
     * `footer-menu` (and `off-canvas-menu`, which Arena doesn't need — its
     * off-canvas panel re-renders `main-menu` directly). Registering
     * `footer-menu` here lets a footer-specific menu assignment carry over.
     */
    public function test_registers_optional_footer_menu_location(): void {
        $this->setExpectedIncorrectUsage("add_theme_support( 'title-tag' )");
        do_action('after_setup_theme');
        $this->assertArrayHasKey('footer-menu', get_registered_nav_menus());
    }

    /** footer.php must fall back to `main-menu` when nothing is assigned to `footer-menu`. */
    public function test_footer_menu_location_falls_back_to_main_menu_when_unassigned(): void {
        $this->assertSame('main-menu', Setup::footerMenuLocation());
    }

    /** footer.php must use `footer-menu` once the site owner actually assigns something there. */
    public function test_footer_menu_location_uses_footer_menu_when_assigned(): void {
        register_nav_menus(['footer-menu' => 'Footer']);
        $menuId = wp_create_nav_menu('Arena Footer Menu ' . __METHOD__);
        $locations = get_theme_mod('nav_menu_locations', []);
        $locations['footer-menu'] = $menuId;
        set_theme_mod('nav_menu_locations', $locations);

        $this->assertSame('footer-menu', Setup::footerMenuLocation());
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
