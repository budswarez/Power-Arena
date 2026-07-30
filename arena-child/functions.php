<?php
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'arena-child',
        get_stylesheet_directory_uri() . '/style.css',
        ['arena-main'],
        wp_get_theme()->get('Version')
    );
}, 20);
