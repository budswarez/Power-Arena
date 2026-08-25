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

    // The STYLESHEET (unlike the template/parent theme dir, always
    // 'arena') is configurable via an optional ARENA_PREVIEW_STYLESHEET
    // constant — mirrors Arena\Preview::resolveStylesheet(), which this
    // file can't call directly for the same autoloader-timing reason.
    // Without this, the preview always forced the PARENT theme even if
    // production runs `arena-child` as the active theme — previewing
    // something other than what actually ships.
    $stylesheet = (defined('ARENA_PREVIEW_STYLESHEET') && is_string(ARENA_PREVIEW_STYLESHEET) && ARENA_PREVIEW_STYLESHEET !== '')
        ? ARENA_PREVIEW_STYLESHEET
        : 'arena';
    add_filter('stylesheet', static fn (): string => $stylesheet);

    // A leaked `?arena_preview=<token>` URL (Search Console, a Referer
    // header, someone pasting the link) would otherwise let the whole
    // site be crawled/indexed under the NEW theme while the old one is
    // still the one actually live for everyone else — duplicate content,
    // plus the token itself ending up in third-party logs. With a cache
    // layer (LiteSpeed) in front, the theme-swapped HTML is also
    // cacheable unless explicitly told not to.
    header('X-Robots-Tag: noindex, nofollow', true);
    nocache_headers();

    // Belt-and-braces: nocache_headers()/X-Robots-Tag cover the HTTP
    // response, but a `<meta name="robots">` in <head> keeps the same
    // guarantee even if some intermediary (cache, CDN, proxy) strips
    // headers it doesn't recognise before the response reaches the
    // browser/crawler.
    add_action('wp_head', static function (): void {
        echo '<meta name="robots" content="noindex,nofollow">' . "\n";
    }, 1);
});
