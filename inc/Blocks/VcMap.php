<?php
declare(strict_types=1);

namespace Arena\Blocks;

/**
 * `vc_map()` definitions for the 4 Arena `[bs-*]` shortcodes (registered in
 * `Arena\Blocks\Shortcodes`), so they become editable inside the WPBakery
 * ("js_composer") visual editor. Purely additive: WPBakery itself still owns
 * rendering the shortcode UI in wp-admin; the actual frontend render stays
 * with `Arena\Listing\Renderer` via `Shortcodes::register()`.
 */
final class VcMap {
    /**
     * Registers the 4 maps on `vc_before_init`, the hook WPBakery documents
     * for third-party `vc_map()` calls. No-op (both here and inside the
     * deferred callback) when WPBakery isn't installed/active, since
     * `vc_map()` itself wouldn't exist to call.
     */
    public static function register(): void {
        add_action('vc_before_init', static function (): void {
            if (!function_exists('vc_map')) {
                return;
            }

            foreach (self::maps() as $map) {
                vc_map($map);
            }
        });
    }

    /**
     * Pure: the 4 `vc_map()` definition arrays, one per `[bs-*]` shortcode
     * tag registered in `Arena\Blocks\Shortcodes::register()`. No WP calls.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function maps(): array {
        return [
            [
                'name'     => 'BS Modern Grid Listing',
                'base'     => 'bs-modern-grid-listing-7',
                'category' => 'Arena',
                'params'   => self::commonParams(),
            ],
            [
                'name'     => 'BS Mix Listing',
                'base'     => 'bs-mix-listing-3-1',
                'category' => 'Arena',
                'params'   => self::commonParams(),
            ],
            [
                'name'     => 'BS Blog Listing',
                'base'     => 'bs-blog-listing-1',
                'category' => 'Arena',
                'params'   => [...self::commonParams(), self::columnsParam()],
            ],
            [
                'name'     => 'BS Grid Listing',
                'base'     => 'bs-grid-listing-1',
                'category' => 'Arena',
                'params'   => [...self::commonParams(), self::columnsParam()],
            ],
        ];
    }

    /**
     * Params shared by all 4 blocks, matching `Shortcodes::COMMON_DEFAULTS`
     * plus the per-layout `count`/`featured_image`/`show_excerpt` attributes.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function commonParams(): array {
        return [
            [
                'type'       => 'textfield',
                'heading'    => 'Título',
                'param_name' => 'title',
            ],
            [
                'type'       => 'textfield',
                'heading'    => 'Categoria (ID do termo)',
                'param_name' => 'category',
            ],
            [
                'type'       => 'textfield',
                'heading'    => 'Tag',
                'param_name' => 'tag',
            ],
            [
                'type'       => 'textfield',
                'heading'    => 'Quantidade de posts',
                'param_name' => 'count',
            ],
            [
                'type'       => 'checkbox',
                'heading'    => 'Imagem destacada',
                'param_name' => 'featured_image',
                'value'      => ['Exibir imagem destacada' => '1'],
            ],
            [
                'type'       => 'checkbox',
                'heading'    => 'Exibir resumo',
                'param_name' => 'show_excerpt',
                'value'      => ['Exibir resumo' => '1'],
            ],
            [
                'type'       => 'colorpicker',
                'heading'    => 'Cor do título',
                'param_name' => 'heading_color',
            ],
            [
                'type'       => 'dropdown',
                'heading'    => 'Ordem',
                'param_name' => 'order',
                'value'      => ['DESC' => 'DESC', 'ASC' => 'ASC'],
            ],
            [
                'type'       => 'dropdown',
                'heading'    => 'Ordenar por',
                'param_name' => 'order_by',
                'value'      => ['Data' => 'date', 'Aleatório' => 'rand'],
            ],
            [
                'type'       => 'dropdown',
                'heading'    => 'Esquema de cores do texto',
                'param_name' => 'bs-text-color-scheme',
                'value'      => ['Padrão' => '', 'Claro' => 'light', 'Escuro' => 'dark'],
            ],
        ];
    }

    /** Extra param only relevant to layouts that read `columns` (blog, grid). */
    private static function columnsParam(): array {
        return [
            'type'       => 'textfield',
            'heading'    => 'Colunas',
            'param_name' => 'columns',
        ];
    }
}
