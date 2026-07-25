<?php
declare(strict_types=1);

namespace Arena\Listing;

final class Renderer {
    private const LAYOUTS = ['modern-grid', 'modern-grid-1', 'mix', 'blog', 'grid', 'archive'];
    private const DEFAULT_LAYOUT = 'grid';

    /**
     * Short date format shared by every card template
     * (template-parts/card/{hero,featured,excerpt,text}.php) AND the
     * single-article meta (template-parts/single/meta.php, via
     * `articleDate()` below) — e.g. "24 Jul, 2026". One constant instead of
     * the same literal `'j M, Y'` duplicated in 5 places, so the two
     * surfaces can't drift apart again (they had: the article meta used to
     * fall back to WP's own `date_format` option, rendering the English
     * long form "July 24, 2026").
     */
    private const CARD_DATE_FORMAT = 'j M, Y';

    /**
     * Post IDs already rendered by an EARLIER `[bs-*]` block on the SAME
     * request. Publisher's `disable_duplicate="1"` reads this list to
     * exclude posts a previous block already showed (the real home:
     * hero `[bs-modern-grid-listing-7 ... disable_duplicate="1"]` then
     * "Últimas notícias" `[bs-grid-listing-1 ... disable_duplicate="0"]` —
     * without this, the 5 hero posts reappear in the 8-post grid on every
     * load). Lives on the Renderer (which already does WordPress-facing
     * orchestration), never on Query, which stays a pure attrs->args
     * mapper with no per-request state.
     *
     * Every block appends its own rendered IDs here regardless of its own
     * `disable_duplicate` value — only whether a block's OWN query
     * excludes from this list depends on its own flag. That reproduces
     * "later blocks avoid what earlier blocks already showed" without
     * needing to know Publisher's exact internal contribution rule.
     *
     * @var int[]
     */
    private static array $shown = [];

    /**
     * Test isolation helper: clears the per-request "already shown" list.
     * Must be called between independent test cases (and is safe to call
     * in production at the start of a fresh request — but nothing in this
     * theme currently needs that, since a PHP request always starts with
     * an empty static).
     */
    public static function resetShown(): void {
        self::$shown = [];
    }

    /**
     * Renderiza uma listagem de posts para um dos 4 layouts do Publisher
     * (paridade visual), a partir dos atributos crus de um shortcode `[bs-*]`.
     *
     * Constrói os args via Query::args() (injeta time() aqui — Renderer não é
     * a unidade pura), executa uma WP_Query real e delega a marcação de cada
     * card para template-parts/card/{featured,text,excerpt}.php via
     * get_template_part(), dentro do wrapper de template-parts/listing/*.php
     * correspondente ao layout.
     *
     * @param array<string, mixed> $atts
     */
    public static function render(string $layout, array $atts): string {
        $layout = in_array($layout, self::LAYOUTS, true) ? $layout : self::DEFAULT_LAYOUT;

        $args = Query::args($atts, time());

        if (self::disableDuplicate($atts)) {
            $args['post__not_in'] = array_merge($args['post__not_in'] ?? [], self::$shown);
        }

        $query = new \WP_Query($args);
        $options = self::buildOptions($atts);

        ob_start();
        get_template_part('template-parts/listing/' . $layout, null, [
            'query'   => $query,
            'options' => $options,
        ]);
        $html = (string) ob_get_clean();

        self::$shown = array_merge(self::$shown, self::queriedPostIds($query));

        wp_reset_postdata();

        return $html;
    }

    /** @param array<string, mixed> $atts */
    private static function disableDuplicate(array $atts): bool {
        return self::boolOrDefault($atts['disable_duplicate'] ?? null, false);
    }

    /** @return int[] */
    private static function queriedPostIds(\WP_Query $query): array {
        return array_map(static fn (\WP_Post $post): int => (int) $post->ID, $query->posts);
    }

    /**
     * @param array<string, mixed> $atts
     * @return array<string, mixed>
     */
    private static function buildOptions(array $atts): array {
        return [
            'heading_html'   => self::renderHeading($atts),
            'columns'        => Attrs::intOrDefault($atts['columns'] ?? null, 4, 1),
            'featured_image' => self::boolOrDefault($atts['featured_image'] ?? null, true),
            'show_excerpt'   => self::boolOrDefault($atts['show_excerpt'] ?? null, true),
            'dark_scheme'    => self::isDarkScheme($atts['bs-text-color-scheme'] ?? null),
        ];
    }

    /**
     * `heading_color` colore a BARRA do heading (o `::before` skewed de
     * `.section-heading.sh-t6.sh-s6`, ver main.css), não o texto — medido
     * contra a referência pública, onde o texto do heading fica sempre
     * branco sobre a barra colorida. O `style="color:..."` fica no `<div
     * class="section-heading">` (não no `.h-text` interno) para que a CSS
     * da barra possa ler `background:currentColor` a partir do elemento
     * pai; o texto em si é forçado a branco via regra própria de
     * `.section-heading.sh-t6.sh-s6 .h-text`.
     *
     * The colored flag is followed by a light-grey continuation bar
     * (`.h-flag-continuation`) that stretches to the right edge of the
     * column — measured on the reference, every heading flag (the dark
     * "Evento Pichau Arena 2026" bar above the banner, and the 3 category-
     * column flags) has this same grey bar picking up where the colored
     * part ends. It's a plain sibling `<span>`, not a 3rd pseudo-element on
     * `.section-heading` (which already uses both `::before`, the flag's
     * own rectangle, and `::after`, the pointed notch), wrapped together in
     * `.section-heading-row` so a flex `flex:1 1 auto` on the span can fill
     * whatever width the flag itself doesn't use.
     *
     * `heading_color` is validated with `sanitize_hex_color()` before it
     * reaches the `style` attribute. The WPBakery/ACF field behind it is a
     * `colorpicker`, so a hex string is the only value the UI is meant to
     * produce — but nothing stops a hand-edited shortcode from putting
     * arbitrary text there. `esc_attr()` alone stops it from breaking OUT
     * of the `style="..."` attribute, but does NOT stop it from injecting
     * extra CSS declarations inside that same attribute value (e.g.
     * `heading_color="red;position:fixed;inset:0;background:#000"` still
     * passes `esc_attr()` unchanged and lands verbatim in `style`). An
     * invalid value simply drops the inline style — the heading still
     * renders, just without a custom colour.
     *
     * @param array<string, mixed> $atts
     */
    private static function renderHeading(array $atts): string {
        $title = isset($atts['title']) ? trim((string) $atts['title']) : '';
        if ($title === '') {
            return '';
        }

        $rawColor = isset($atts['heading_color']) ? trim((string) $atts['heading_color']) : '';
        $color = $rawColor !== '' ? sanitize_hex_color($rawColor) : '';
        $wrapperStyle = (is_string($color) && $color !== '') ? ' style="color:' . esc_attr($color) . '"' : '';

        $link = self::headingLink($atts);

        $flagInner = $link !== ''
            ? '<a class="h-text main-link" href="' . esc_url($link) . '">' . esc_html($title) . '</a>'
            : '<span class="h-text">' . esc_html($title) . '</span>';

        return '<div class="section-heading-row"><div class="section-heading sh-t6 sh-s6"' . $wrapperStyle . '>'
            . $flagInner . '</div><span class="h-flag-continuation" aria-hidden="true"></span></div>';
    }

    /** @param array<string, mixed> $atts */
    private static function headingLink(array $atts): string {
        $category = isset($atts['category']) ? (string) $atts['category'] : '';
        if ($category === '' || (int) $category <= 0) {
            return '';
        }

        $link = get_category_link((int) $category);

        return is_string($link) ? $link : '';
    }

    /**
     * Guards `has_post_thumbnail()`: also confirms the attached file still
     * exists on disk. A post can carry a `_thumbnail_id` that points at an
     * attachment whose file never made it into `wp-content/uploads` (a
     * partial import, media not synced from the live site) — in that case
     * `has_post_thumbnail()` still returns true, and a card template that
     * trusts it alone renders an `<img>` with a 404 src: the browser shows
     * a broken-image box instead of the card's intended placeholder.
     * Card templates should call this instead of `has_post_thumbnail()`
     * directly, and fall back to a `.thumb-placeholder` block when false.
     */
    public static function hasUsableThumbnail(int $postId): bool {
        if (!has_post_thumbnail($postId)) {
            return false;
        }

        $attachmentId = (int) get_post_thumbnail_id($postId);
        if ($attachmentId <= 0) {
            return false;
        }

        $file = get_attached_file($attachmentId);

        return is_string($file) && $file !== '' && file_exists($file);
    }

    /**
     * Inline SVG speech-bubble icon printed before the comment count in a
     * card's `.post-meta` (`template-parts/card/featured.php` and
     * `card/excerpt.php`) — measured against the reference, which shows a
     * small bubble glyph before the number (e.g. "💬 0"), never a bare
     * digit. A shared helper (rather than duplicating the markup in both
     * card templates) so there's exactly one clean-room shape to maintain;
     * inline (not an icon-font glyph or external asset) since no icon font
     * is enqueued — an `<i class="fa …">` with nothing backing it renders
     * empty, which is the current bug this fixes. `aria-hidden`: the
     * comment count text itself already conveys the count; the icon is
     * purely decorative.
     */
    /**
     * Formats a post's date with the same short format used across every
     * card AND the single-article meta — see `CARD_DATE_FORMAT` above.
     */
    public static function articleDate(int $postId): string {
        return get_the_date(self::CARD_DATE_FORMAT, $postId);
    }

    public static function commentIcon(): string {
        return '<svg class="icon-comment" width="14" height="13" viewBox="0 0 16 15" aria-hidden="true" focusable="false">'
            . '<path fill="currentColor" d="M2 1.5h12A1.5 1.5 0 0 1 15.5 3v6A1.5 1.5 0 0 1 14 10.5H6.8L3 14v-3.5H2A1.5 1.5 0 0 1 .5 9V3A1.5 1.5 0 0 1 2 1.5z"/>'
            . '</svg>';
    }

    private static function boolOrDefault(mixed $value, bool $default): bool {
        if ($value === null || $value === '') {
            return $default;
        }

        return !in_array(strtolower((string) $value), ['0', 'false', 'no', 'off'], true);
    }

    /**
     * `bs-text-color-scheme` names the TEXT color, not the section's own
     * background: the shortcode passes `light` (light/white text) for
     * sections meant to sit on a dark background (e.g. "Últimas notícias")
     * and leaves it empty for ordinary light-background sections. So a
     * light *text* scheme is what needs the dark-section wrapper class.
     */
    private static function isDarkScheme(mixed $value): bool {
        return is_string($value) && strtolower(trim($value)) === 'light';
    }
}
