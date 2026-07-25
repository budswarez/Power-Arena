<?php
declare(strict_types=1);

namespace Arena;

final class Setup {
    public static function register(): void {
        add_action('after_setup_theme', [self::class, 'themeSupports']);
        add_action('after_setup_theme', [self::class, 'menusAndSizes']);
        add_action('widgets_init', [self::class, 'sidebars']);
        add_filter('body_class', [self::class, 'shellBodyClasses']);
    }

    public static function themeSupports(): void {
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
    }

    /**
     * Locations use the same slugs Publisher (the legacy theme) used —
     * `main-menu`, `top-menu`, `resp-menu` — so existing menu assignments
     * carry over on migration without the site owner reassigning anything.
     * `arena_primary` is kept as an extra/alias location.
     */
    public static function menusAndSizes(): void {
        register_nav_menus([
            'arena_primary' => __('Menu Principal', 'arena'),
            'main-menu'     => __('Menu Principal (topo)', 'arena'),
            'top-menu'      => __('Menu Superior', 'arena'),
            'resp-menu'     => __('Menu Responsivo', 'arena'),
        ]);
        add_image_size('arena-card', 760, 428, true);
    }

    public static function sidebars(): void {
        register_sidebar([
            'id'            => 'arena-primary',
            'name'          => __('Sidebar Principal', 'arena'),
            'before_widget' => '<section id="%1$s" class="widget %2$s">',
            'after_widget'  => '</section>',
            'before_title'  => '<h3 class="widget__title">',
            'after_title'   => '</h3>',
        ]);
    }

    /**
     * Adds the reference's 2-column-shell body classes on single/archive/
     * search/page — matches the reference's measured `page-layout-2-col
     * page-layout-2-col-right active-sticky-sidebar` naming so the shell's
     * CSS hooks (main.css) line up with the reference. `is_archive()`
     * already covers category/tag/date/author/custom-taxonomy archives, so
     * no separate `is_author()` check is needed. `is_page()` covers
     * ordinary WP pages (page.php, GAP 3), which use the SAME shell as
     * posts per the reference.
     *
     * Deliberately excludes the front page even though a static front page
     * IS a Page and so also satisfies `is_page()`: the home stays on
     * front-page.php's own full-width WPBakery layout, never the 2-column
     * shell, so `!is_front_page()` guards that case explicitly rather than
     * relying on is_single()/is_archive()/is_search() never matching it (as
     * used to be the whole guard, back when page.php had no shell classes
     * of its own to worry about).
     *
     * @param array<int, string> $classes
     * @return array<int, string>
     */
    public static function shellBodyClasses(array $classes): array {
        if (is_single() || is_archive() || is_search() || (is_page() && !is_front_page())) {
            $classes[] = 'page-layout-2-col';
            $classes[] = 'page-layout-2-col-right';
            $classes[] = 'active-sticky-sidebar';
        }

        return $classes;
    }
}
