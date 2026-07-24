<?php
declare(strict_types=1);

use Arena\Menus\MegaMenuWalker;

class MegaMenuWalkerTest extends WP_UnitTestCase {
    /**
     * Builds a real nav menu (parent + child + a childless sibling, via the
     * core wp_create_nav_menu()/wp_update_nav_menu_item() APIs), renders it
     * through wp_nav_menu() with MegaMenuWalker, and asserts on the actual
     * HTML string — no stubbed walker internals.
     */
    public function test_parent_with_child_gets_has_children_class_and_child_is_nested_in_sub_menu(): void {
        $menu_id = wp_create_nav_menu('Mega Menu Test ' . __METHOD__);
        $this->assertIsInt($menu_id);

        $parent_id = wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title'  => 'Parent Item',
            'menu-item-status' => 'publish',
        ]);
        $this->assertIsInt($parent_id);

        wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title'     => 'Child Item',
            'menu-item-parent-id' => $parent_id,
            'menu-item-status'    => 'publish',
        ]);

        // A sibling with no children must NOT get the has-children class.
        wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title'  => 'Leaf Item',
            'menu-item-status' => 'publish',
        ]);

        $html = wp_nav_menu([
            'menu'        => $menu_id,
            'echo'        => false,
            'container'   => false,
            'menu_class'  => 'main-menu menu bsm-pure clearfix',
            'walker'      => new MegaMenuWalker(),
            'fallback_cb' => false,
        ]);

        $this->assertIsString($html);

        // Exactly one item (the parent) has children among the three top-
        // level items created, so the class must appear exactly once.
        $this->assertSame(1, substr_count($html, 'menu-item-has-children'));
        $this->assertStringContainsString('<ul class="sub-menu">', $html);

        // The child item's <li> must be inside the sub-menu block, not a
        // top-level sibling: it must sit between the sub-menu's opening
        // <ul ...> and its matching closing </ul>.
        $sub_menu_start = strpos($html, '<ul class="sub-menu">');
        $this->assertNotFalse($sub_menu_start);
        $sub_menu_end = strpos($html, '</ul>', $sub_menu_start);
        $this->assertNotFalse($sub_menu_end);
        $child_pos = strpos($html, 'Child Item');
        $this->assertNotFalse($child_pos);
        $this->assertGreaterThan($sub_menu_start, $child_pos);
        $this->assertLessThan($sub_menu_end, $child_pos);

        // The leaf item's <li> must sit outside (after) the sub-menu block,
        // confirming it stayed a top-level item.
        $leaf_pos = strpos($html, 'Leaf Item');
        $this->assertNotFalse($leaf_pos);
        $this->assertGreaterThan($sub_menu_end, $leaf_pos);
    }
}
