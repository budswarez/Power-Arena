<?php
declare(strict_types=1);

namespace Arena\Blocks;

use Arena\Listing\Renderer;

final class Shortcodes {
    /**
     * Atributos comuns a todos os 4 shortcodes `[bs-*]` do Publisher
     * (clean-room), independentemente do layout.
     *
     * @var array<string, string>
     */
    private const COMMON_DEFAULTS = [
        'order'                 => 'DESC',
        'heading_color'         => '',
        'title'                 => '',
        'category'              => '',
        'tag'                   => '',
        'offset'                => '',
        'post_ids'              => '',
        'ignore_sticky_posts'   => '1',
        'time_filter'           => '',
        'bs-text-color-scheme'  => '',
    ];

    /**
     * Registra os 4 shortcodes de listagem do Publisher, delegando cada um
     * ao motor próprio do Arena (Arena\Listing\Renderer). `add_shortcode()`
     * já sobrescreve um registro anterior para a mesma tag, então chamar
     * `register()` mais de uma vez é seguro (idempotente).
     */
    public static function register(): void {
        add_shortcode('bs-modern-grid-listing-7', [self::class, 'renderModernGrid']);
        add_shortcode('bs-mix-listing-3-1', [self::class, 'renderMix']);
        add_shortcode('bs-blog-listing-1', [self::class, 'renderBlog']);
        add_shortcode('bs-grid-listing-1', [self::class, 'renderGrid']);
    }

    /** @param array<string, mixed>|string $atts */
    public static function renderModernGrid(array|string $atts): string {
        $atts = shortcode_atts(
            self::COMMON_DEFAULTS + [
                'count'          => '5',
                'order_by'       => 'date',
                'featured_image' => '1',
                'show_excerpt'   => '0',
            ],
            $atts,
            'bs-modern-grid-listing-7'
        );

        return Renderer::render('modern-grid', $atts);
    }

    /** @param array<string, mixed>|string $atts */
    public static function renderMix(array|string $atts): string {
        $atts = shortcode_atts(
            self::COMMON_DEFAULTS + [
                'count'          => '4',
                'order_by'       => 'date',
                'featured_image' => '0',
                'show_excerpt'   => '0',
            ],
            $atts,
            'bs-mix-listing-3-1'
        );

        return Renderer::render('mix', $atts);
    }

    /** @param array<string, mixed>|string $atts */
    public static function renderBlog(array|string $atts): string {
        $atts = shortcode_atts(
            self::COMMON_DEFAULTS + [
                'count'          => '4',
                'order_by'       => 'rand',
                'featured_image' => '1',
                'show_excerpt'   => '1',
                'columns'        => '1',
            ],
            $atts,
            'bs-blog-listing-1'
        );

        return Renderer::render('blog', $atts);
    }

    /** @param array<string, mixed>|string $atts */
    public static function renderGrid(array|string $atts): string {
        $atts = shortcode_atts(
            self::COMMON_DEFAULTS + [
                'count'          => '8',
                'order_by'       => 'date',
                'featured_image' => '0',
                'show_excerpt'   => '0',
                'columns'        => '4',
            ],
            $atts,
            'bs-grid-listing-1'
        );

        return Renderer::render('grid', $atts);
    }
}
