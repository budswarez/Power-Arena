<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Accessible search form (Fatia 2B Task 5): `role="search"`, a
 * visually-hidden `<label>` bound to the input via a real `for`/`id` pair,
 * `name="s"`, submitting a plain GET to `home_url('/')` (so pretty
 * permalinks don't matter), plus a submit button carrying the same
 * magnifier icon as the header (`Arena\Icons::search()`).
 *
 * Single source of markup for every call site in the theme:
 *  - WordPress's own `get_search_form()` auto-discovers this file (theme
 *    root `searchform.php` beats WP core's built-in default markup) — used
 *    with NO args by search.php's empty state and by 404.php, so both get
 *    the plain `class="search-form"` shape styled in main.css.
 *  - template-parts/header/search.php calls `get_search_form($args)` with
 *    `form_id`/`form_class`/`input_id` overrides so the SAME form also
 *    slots into the header's search-toggle dropdown
 *    (`.header-search-form`, `#header-search-form`,
 *    `#header-search-input` — the exact ids/classes `initSearchToggle()`
 *    in main.js and the header's own CSS already key off of).
 *
 * `$args` flows through untouched regardless of entry point: WP core's
 * `get_search_form($args)` does `require $template;` INSIDE its own
 * function body (after merging `$args` with its defaults), and
 * `get_template_part($slug, $name, $args)` → `locate_template(..., $args)`
 * → `load_template($file, $require_once, $args)` does the same — both
 * mechanisms are a plain PHP `require` sharing the caller's scope, so
 * `$args` set there is visible here exactly like every other
 * `template-parts/*.php` partial in this theme.
 *
 * @var array<string, mixed> $args {
 *     @type string $form_id    Optional id="" on the <form>. Omitted by default.
 *     @type string $form_class Full replacement for the <form>'s class attribute. Defaults to "search-form".
 *     @type string $input_id   Optional id shared by the <label for> and the <input>. Auto-generated (wp_unique_id()) when omitted.
 * }
 */

$args = is_array($args ?? null) ? $args : [];

$formId = isset($args['form_id']) ? trim((string) $args['form_id']) : '';
$formClass = isset($args['form_class']) && trim((string) $args['form_class']) !== ''
    ? trim((string) $args['form_class'])
    : 'search-form';
$inputId = isset($args['input_id']) && trim((string) $args['input_id']) !== ''
    ? trim((string) $args['input_id'])
    : wp_unique_id('search-field-');

// get_search_query(false): the raw query, escaped exactly once below —
// get_search_query() with its default arg already returns an esc_attr()'d
// string, and escaping that again (esc_attr on top of esc_attr) would
// double-encode any "&"/quote a real search term contains.
$searchQuery = get_search_query(false);
?>
<form
    role="search"
    method="get"
    class="<?php echo esc_attr($formClass); ?>"
    <?php if ($formId !== ''): ?>id="<?php echo esc_attr($formId); ?>"<?php endif; ?>
    action="<?php echo esc_url(home_url('/')); ?>"
>
    <label class="screen-reader-text" for="<?php echo esc_attr($inputId); ?>">
        <?php esc_html_e('Buscar por:', 'arena'); ?>
    </label>
    <input
        type="search"
        id="<?php echo esc_attr($inputId); ?>"
        class="search-field"
        name="s"
        placeholder="<?php esc_attr_e('Buscar…', 'arena'); ?>"
        value="<?php echo esc_attr($searchQuery); ?>"
    >
    <button type="submit" class="search-submit">
        <span class="screen-reader-text"><?php esc_html_e('Buscar', 'arena'); ?></span>
        <?php echo \Arena\Icons::search(); ?>
    </button>
</form>
