<?php
declare(strict_types=1);

namespace Arena;

final class OptionsPanel {
    public static function register(): void {
        add_action('acf/init', [self::class, 'boot']);
    }

    public static function boot(): void {
        if (!function_exists('acf_add_options_page')) { return; }
        acf_add_options_page([
            'page_title' => 'Arena',
            'menu_title' => 'Arena',
            'menu_slug'  => 'arena-options',
            'capability' => 'edit_theme_options',
            'icon_url'   => 'dashicons-shield',
        ]);
        acf_add_local_field_group([
            'key'      => 'group_arena_options',
            'title'    => 'Opções do Arena',
            'fields'   => self::fields(),
            'location' => [[['param' => 'options_page', 'operator' => '==', 'value' => 'arena-options']]],
        ]);
    }

    /**
     * Definição pura dos campos (testável). Os 3 campos abaixo da logo são
     * lidos por inc/Options.php e efetivamente aplicados via
     * Arena\Assets::printInlineTokens() (accent/base font — injeta um
     * `:root{…}` inline no `wp_head`) e Arena\Options::sidebarLayout()
     * (posição da sidebar — repassado como `layout` para o shell 2 colunas
     * por todo template que o usa; ver task-review-fixes-3, FIX 1).
     *
     * `arena_base_font` é `select` com 2 opções fixas, não `text` livre: um
     * seletor de fonte livre exigiria auto-hospedar QUALQUER família que o
     * dono do site digitasse, fora do escopo deste tema (que já auto-
     * hospeda Barlow/Oswald, ver assets/fonts/ e Arena\Assets) — a escolha
     * fica entre o par próprio do tema e uma pilha de fontes do sistema
     * (zero rede adicional).
     */
    public static function fields(): array {
        return [
            ['key' => 'field_arena_logo', 'name' => 'arena_logo', 'label' => 'Logo', 'type' => 'image', 'return_format' => 'id'],
            ['key' => 'field_arena_accent', 'name' => 'arena_accent_color', 'label' => 'Cor de destaque', 'type' => 'color_picker', 'default_value' => Options::DEFAULT_ACCENT],
            [
                'key' => 'field_arena_font',
                'name' => 'arena_base_font',
                'label' => 'Fonte base',
                'type' => 'select',
                'choices' => [
                    'barlow-oswald' => 'Barlow + Oswald (padrão do tema)',
                    'system'        => 'Fontes do sistema',
                ],
                'default_value' => Options::DEFAULT_BASE_FONT,
            ],
            [
                'key' => 'field_arena_sidebar',
                'name' => 'arena_sidebar_position',
                'label' => 'Posição da sidebar',
                'type' => 'select',
                'choices' => ['right' => 'Direita', 'left' => 'Esquerda', 'none' => 'Sem sidebar'],
                'default_value' => Options::DEFAULT_SIDEBAR_POSITION,
            ],
        ];
    }
}
