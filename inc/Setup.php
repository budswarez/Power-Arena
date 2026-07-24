<?php
declare(strict_types=1);

namespace Arena;

final class Setup {
    public static function register(): void {
        add_action('after_setup_theme', [self::class, 'themeSupports']);
        add_action('after_setup_theme', [self::class, 'menusAndSizes']);
        add_action('widgets_init', [self::class, 'sidebars']);
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
}
