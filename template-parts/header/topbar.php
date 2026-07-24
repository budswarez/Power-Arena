<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Top bar: renders the `top-menu` theme location (the "Link Pichau" menu on
 * the live site). Silently renders nothing if no menu is assigned yet —
 * `fallback_cb => false` avoids WordPress falling back to a page list.
 */
?>
<div class="top-bar">
    <nav class="top-menu-container" aria-label="<?php esc_attr_e('Menu superior', 'arena'); ?>">
        <?php
        wp_nav_menu([
            'theme_location' => 'top-menu',
            'container'      => false,
            'menu_class'     => 'top-menu menu clearfix',
            'fallback_cb'    => false,
        ]);
        ?>
    </nav>
</div>
