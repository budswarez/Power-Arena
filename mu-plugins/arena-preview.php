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
    $expected = (defined('ARENA_PREVIEW_TOKEN') && is_string(ARENA_PREVIEW_TOKEN)) ? ARENA_PREVIEW_TOKEN : null;
    $raw      = $_GET['arena_preview'] ?? null;
    $param    = is_string($raw) ? (string) wp_unslash($raw) : null;
    $loggedInCan = is_user_logged_in() && current_user_can('edit_theme_options');

    $enable = $loggedInCan
        || ($expected !== null && $expected !== '' && is_string($param) && hash_equals($expected, $param));

    if (!$enable) { return; }

    // Literal 'arena' kept inline on purpose: this runs at 'setup_theme',
    // before themes/arena/functions.php's own autoloader is available, so
    // Arena\Preview::THEME can't be referenced directly here. That constant
    // is the documented single source of truth for this slug — keep the
    // two in sync (see Arena\Preview's own docblock + PreviewTest).
    add_filter('template', static fn (): string => 'arena');
    add_filter('stylesheet', static fn (): string => 'arena');
});
