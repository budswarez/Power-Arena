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
     * search — matches the reference's measured `page-layout-2-col
     * page-layout-2-col-right active-sticky-sidebar` naming so the shell's
     * CSS hooks (main.css) line up with the reference. `is_archive()`
     * already covers category/tag/date/author/custom-taxonomy archives, so
     * no separate `is_author()` check is needed.
     *
     * Deliberately does NOT touch the home page's classes: is_front_page()/
     * is_page() (the front page's static Page) never satisfy
     * is_single()/is_archive()/is_search(), so this never fires there.
     *
     * @param array<int, string> $classes
     * @return array<int, string>
     */
    public static function shellBodyClasses(array $classes): array {
        if (is_single() || is_archive() || is_search()) {
            $classes[] = 'page-layout-2-col';
            $classes[] = 'page-layout-2-col-right';
            $classes[] = 'active-sticky-sidebar';
        }

        return $classes;
    }
}
