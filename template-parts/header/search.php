<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Search toggle: static markup only (matches the live site's search-icon
 * button + hidden form pattern). AJAX-driven live search is out of scope
 * for this task; the toggle behaviour itself belongs to theme JS added in
 * a later task.
 */
?>
<div class="header-search">
    <button
        type="button"
        class="menu-handler search-toggle"
        aria-expanded="false"
        aria-controls="header-search-form"
    >
        <span class="screen-reader-text"><?php esc_html_e('Buscar', 'arena'); ?></span>
        <i class="icon-search" aria-hidden="true"></i>
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
