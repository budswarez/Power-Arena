<?php
declare(strict_types=1);

namespace Arena\Menus;

/**
 * Mega-menu walker for the `main-menu` theme location.
 *
 * Walker_Nav_Menu already renders the standard menu-item markup (item-type/
 * item-object classes, current-menu-item state, etc.) and already wraps
 * submenus as `<ul class="sub-menu">` via start_lvl(); Walker::walk() also
 * already recurses through however many levels the menu actually has, so
 * "up to 3 levels" needs no special-casing here — it is simply how deep the
 * mega menu's own menu items go.
 *
 * The one thing that is not guaranteed by every render path is the
 * `menu-item-has-children` class on parent `<li>` elements (it is only
 * added by wp_nav_menu()'s own item-classification pass, which some render
 * paths — including a bare Walker::walk() call — skip). We add it
 * ourselves, defensively, from the `has_children` flag the base Walker
 * class always sets before calling start_el().
 */
final class MegaMenuWalker extends \Walker_Nav_Menu {
    public function start_el(&$output, $data_object, $depth = 0, $args = null, $current_object_id = 0) {
        $before = $output;
        parent::start_el($output, $data_object, $depth, $args, $current_object_id);

        $has_children = is_object($args) && !empty($args->has_children);
        if (!$has_children) {
            return;
        }

        $rendered = substr($output, strlen($before));
        if (str_contains($rendered, 'menu-item-has-children')) {
            return;
        }

        $rendered = preg_replace(
            '/(<li[^>]*\bclass="[^"]*)"/',
            '$1 menu-item-has-children"',
            $rendered,
            1
        );

        $output = $before . $rendered;
    }
}
