<?php
declare(strict_types=1);

namespace Arena;

final class Setup {
    public static function register(): void {
        add_action('after_setup_theme', [self::class, 'themeSupports']);
    }

    public static function themeSupports(): void {
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
    }
}
