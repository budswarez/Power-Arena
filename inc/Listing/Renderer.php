<?php
declare(strict_types=1);

namespace Arena\Listing;

final class Renderer {
    private const LAYOUTS = ['modern-grid', 'mix', 'blog', 'grid'];
    private const DEFAULT_LAYOUT = 'grid';

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

        $query = new \WP_Query(Query::args($atts, time()));
        $options = self::buildOptions($atts);

        ob_start();
        get_template_part('template-parts/listing/' . $layout, null, [
            'query'   => $query,
            'options' => $options,
        ]);
        $html = (string) ob_get_clean();

        wp_reset_postdata();

        return $html;
    }

    /**
     * @param array<string, mixed> $atts
     * @return array<string, mixed>
     */
    private static function buildOptions(array $atts): array {
        return [
            'heading_html'   => self::renderHeading($atts),
            'columns'        => self::intOrDefault($atts['columns'] ?? null, 4),
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
     * @param array<string, mixed> $atts
     */
    private static function renderHeading(array $atts): string {
        $title = isset($atts['title']) ? trim((string) $atts['title']) : '';
        if ($title === '') {
            return '';
        }

        $color = isset($atts['heading_color']) ? trim((string) $atts['heading_color']) : '';
        $wrapperStyle = $color !== '' ? ' style="color:' . esc_attr($color) . '"' : '';

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

    private static function intOrDefault(mixed $value, int $default): int {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return $default;
        }
        $int = (int) $value;

        return $int > 0 ? $int : $default;
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
