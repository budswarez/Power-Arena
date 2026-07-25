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
        // Whole-branch review, minor finding #9: an editor can set
        // `hide_title="1"` on a block that ALSO has a non-empty `title` —
        // Publisher hides the heading in that case, but this used to
        // ignore `hide_title` entirely and render it anyway. See
        // Renderer::renderHeading().
        'hide_title'            => '0',
        'category'              => '',
        'tag'                   => '',
        'offset'                => '',
        'post_ids'              => '',
        'ignore_sticky_posts'   => '1',
        'time_filter'           => '',
        'bs-text-color-scheme'  => '',
        'disable_duplicate'     => '0',
        // Per-breakpoint visibility (minor finding #9) — cheap CSS classes
        // added to the block's own wrapper; see
        // Renderer::visibilityClass() and the `.bs-listing-hide-*` rules in
        // main.css for the 3 breakpoints these map to.
        'bs-show-desktop'       => '1',
        'bs-show-tablet'        => '1',
        'bs-show-phone'         => '1',
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

    /**
     * `tabs="cat_filter"` (whole-branch review, minor finding #9): on the
     * home's "Últimas notícias" grid, Publisher renders a category-filter
     * tab strip above the grid (client-side re-filtering the same block
     * between a few categories without a page reload) when this attribute
     * is set. `shortcode_atts()` silently drops it today (not in
     * COMMON_DEFAULTS), same as before this pass — DECISION: left out of
     * scope, not implemented, and documented here rather than silently
     * left alone. Reasoning: this isn't a static-markup gap like
     * `hide_title`/`bs-show-*`/`post_ids` ordering (a few lines each); a
     * real `cat_filter` needs its OWN client-side re-query (either a fresh
     * AJAX request per tab, or pre-rendering every tab's grid up front and
     * toggling visibility), a new REST/admin-ajax endpoint or a
     * substantially larger upfront payload, new markup for the tab strip
     * itself, and its own JS + a11y (tabs need roving tabindex/
     * `aria-selected`, not just a click handler) — a feature-sized addition
     * that doesn't belong in a "minor findings" polish pass. No regression
     * either way: the grid renders exactly as it does today (all posts,
     * unfiltered, no tab strip), just as an unfiltered attribute typo
     * would.
     */
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
