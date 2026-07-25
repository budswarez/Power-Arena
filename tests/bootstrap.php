<?php
declare(strict_types=1);

$_tests_dir = getenv('WP_TESTS_DIR') ?: '/wordpress-phpunit';
require $_tests_dir . '/includes/functions.php';

tests_add_filter('setup_theme', static function (): void {
    switch_theme('arena');
});

// Não requeremos functions.php diretamente aqui: neste ponto ABSPATH ainda não
// está definida (o WP core só a define dentro do require abaixo), e
// functions.php possui a guarda `if (!defined('ABSPATH')) { exit; }`. O core do
// WP já inclui o functions.php do tema ativo automaticamente, imediatamente
// após o action 'setup_theme' disparado pelo switch_theme() acima — ponto em
// que ABSPATH já está definida.
require $_tests_dir . '/includes/bootstrap.php';

if (!function_exists('yoast_breadcrumb')) {
    /**
     * Yoast SEO ships this as a real plugin function; the actual plugin
     * isn't loaded in this isolated PHPUnit environment (composer.json only
     * pulls in `vendor/yoast/phpunit-polyfills`, not the Yoast SEO plugin
     * itself). Every template gates its own call behind
     * `function_exists('yoast_breadcrumb')` (single.php, archive.php,
     * page.php, search.php, 404.php, index.php, attachment.php) precisely
     * so a site WITHOUT Yoast active still renders cleanly — but that same
     * guard means the breadcrumb never renders AT ALL in this test process
     * without a stand-in, silently hiding real breadcrumb-placement bugs
     * (task-uifix BUG 5/6 — the owner wants the breadcrumb rendered ABOVE
     * the content+sidebar row, not squeezed inside the content column) from
     * every full-template test in the suite. A minimal stub, mirroring
     * Yoast's own `$before`/`$after` wrapper signature, restores test
     * parity with the real (Yoast-active) production site.
     */
    function yoast_breadcrumb(string $before = '', string $after = ''): void {
        echo $before . '<a href="' . esc_url(home_url('/')) . '">Home</a>' . $after;
    }
}

if (!function_exists('get_field')) {
    /**
     * task-native-settings: minimal stand-in for ACF's own `get_field()`,
     * scoped to the `'option'` location Arena\Options::get() always passes
     * (the ACF options page, `arena-options`). ACF itself is NOT installed
     * in this isolated PHPUnit environment (same reasoning as the
     * `yoast_breadcrumb()` stub above) — but Arena\Options's precedence
     * chain (theme_mod → ACF → hard default) has a whole rung
     * (`function_exists('get_field')` true, ACF value SET) that is
     * otherwise impossible to exercise from a test, since real ACF options
     * are never present. Mirrors ACF's own default persistence for a
     * simple options-page field closely enough for this: `get_field($key,
     * 'option')` reads the plain `wp_options` row named `$key` (that's
     * literally where ACF puts it for the 'option' location, absent a
     * custom `update/load_value` filter) and returns `null` if unset —
     * tests simulate "ACF has this field set" with a plain
     * `update_option($key, $value)`, exactly like
     * tests/OptionsTest.php's precedence tests do.
     */
    function get_field(string $selector, $post = false) {
        if ($post !== 'option') { return null; }
        $value = get_option($selector);
        return $value === false ? null : $value;
    }
}
