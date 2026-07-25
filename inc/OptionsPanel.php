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

    /** Definição pura dos campos (testável). */
    public static function fields(): array {
        return [
            ['key' => 'field_arena_logo', 'name' => 'arena_logo', 'label' => 'Logo', 'type' => 'image', 'return_format' => 'id'],
            ['key' => 'field_arena_accent', 'name' => 'arena_accent_color', 'label' => 'Cor de destaque', 'type' => 'color_picker', 'default_value' => Options::DEFAULT_ACCENT],
            ['key' => 'field_arena_font', 'name' => 'arena_base_font', 'label' => 'Fonte base', 'type' => 'text', 'default_value' => 'system-ui'],
            ['key' => 'field_arena_sidebar', 'name' => 'arena_sidebar_position', 'label' => 'Posição da sidebar', 'type' => 'select', 'choices' => ['right' => 'Direita', 'left' => 'Esquerda', 'none' => 'Sem sidebar'], 'default_value' => 'right'],
        ];
    }
}
