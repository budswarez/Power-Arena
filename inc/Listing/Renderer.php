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

    /** @param array<string, mixed> $atts */
    private static function renderHeading(array $atts): string {
        $title = isset($atts['title']) ? trim((string) $atts['title']) : '';
        if ($title === '') {
            return '';
        }

        $color = isset($atts['heading_color']) ? trim((string) $atts['heading_color']) : '';
        $style = $color !== '' ? ' style="color:' . esc_attr($color) . '"' : '';

        $link = self::headingLink($atts);

        if ($link !== '') {
            return '<div class="section-heading sh-t6 sh-s6"><a class="h-text main-link" href="'
                . esc_url($link) . '"' . $style . '>' . esc_html($title) . '</a></div>';
        }

        return '<div class="section-heading sh-t6 sh-s6"><span class="h-text"' . $style . '>'
            . esc_html($title) . '</span></div>';
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

    private static function isDarkScheme(mixed $value): bool {
        return is_string($value) && strtolower(trim($value)) === 'dark';
    }
}
