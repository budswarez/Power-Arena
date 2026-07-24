<?php
declare(strict_types=1);
/*
Plugin Name: Arena Preview
Description: Força o tema "arena" apenas para admins logados ou requisições com token válido. Demais visitantes veem o tema ativo.
*/

if (!defined('ABSPATH')) { exit; }

add_action('setup_theme', static function (): void {
    // Autoload do tema não está disponível em mu-plugin: replicar a lógica pura mínima
    // (ver Arena\Preview::shouldPreview em themes/arena/inc/Preview.php).
    $expected = defined('ARENA_PREVIEW_TOKEN') ? ARENA_PREVIEW_TOKEN : null;
    $loggedInCan = is_user_logged_in() && current_user_can('edit_theme_options');
    $param = isset($_GET['arena_preview']) ? (string) wp_unslash($_GET['arena_preview']) : null;

    $enable = $loggedInCan
        || ($expected !== null && $expected !== '' && is_string($param) && hash_equals($expected, $param));

    if (!$enable) { return; }

    add_filter('template', static fn (): string => 'arena');
    add_filter('stylesheet', static fn (): string => 'arena');
});
