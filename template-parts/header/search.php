<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Search toggle: a magnifier icon on the red menu bar (desktop AND mobile —
 * the reference shows it at the bar's right end on both) that reveals a
 * minimal search field submitting to `home_url('/')` with `name="s"`
 * (WordPress's own search query var). The icon is an inline SVG (no icon
 * font is enqueued — `.icon-search` would otherwise render nothing) drawn
 * clean-room to read as a plain magnifying glass. Open/close/Escape wiring
 * is `initSearchToggle()` in main.js (vanilla ES6, no jQuery).
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
        <svg class="icon-search" width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
            <circle cx="8.5" cy="8.5" r="6"></circle>
            <line x1="13.3" y1="13.3" x2="18" y2="18"></line>
        </svg>
    </button>
    <form
        role="search"
        method="get"
        class="header-search-form"
        id="header-search-form"
        action="<?php echo esc_url(home_url('/')); ?>"
    >
        <label class="screen-reader-text" for="header-search-input">
            <?php esc_html_e('Buscar por:', 'arena'); ?>
        </label>
        <input
            type="search"
            id="header-search-input"
            class="search-field"
            name="s"
            placeholder="<?php esc_attr_e('Buscar…', 'arena'); ?>"
            value="<?php echo esc_attr(get_search_query()); ?>"
        >
    </form>
</div>
