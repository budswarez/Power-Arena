<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Search toggle: a magnifier icon on the red menu bar (desktop AND mobile —
 * the reference shows it at the bar's right end on both) that reveals the
 * theme's shared accessible search form (searchform.php, Fatia 2B Task 5)
 * submitting to `home_url('/')` with `name="s"` (WordPress's own search
 * query var). The icon is an inline SVG (no icon font is enqueued —
 * `.icon-search` would otherwise render nothing), shared with the form's
 * own submit button via `Arena\Icons::search()` so there's exactly one
 * clean-room shape to maintain instead of two copies drifting apart.
 * Open/close/Escape wiring is `initSearchToggle()` in main.js (vanilla ES6,
 * no jQuery) — it only depends on the `.header-search`/`.search-toggle`/
 * `.header-search-form`/`.search-field` classes and the
 * `#header-search-form` id below, all still present via the $args passed
 * to get_search_form().
 */
?>
<div class="header-search">
    <button
        type="button"
        class="menu-handler search-toggle"
        aria-expanded="false"
        aria-controls="header-search-form"
        aria-label="<?php esc_attr_e('Buscar', 'arena'); ?>"
    >
        <span class="screen-reader-text"><?php esc_html_e('Buscar', 'arena'); ?></span>
        <?php echo \Arena\Icons::search(); ?>
    </button>
    <?php
    get_search_form([
        'form_id'    => 'header-search-form',
        'form_class' => 'header-search-form',
        'input_id'   => 'header-search-input',
    ]);
    ?>
</div>
