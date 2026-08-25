<?php
declare(strict_types=1);

/**
 * FIX C: `main-menu` is rendered 3 times per document (desktop nav +
 * off-canvas panel in header.php, footer menu in footer.php).
 * Walker_Nav_Menu prints a per-item `id="menu-item-{ID}"` and
 * MegaMenuWalker never touches that id, so without suppressing it on the
 * 2nd/3rd renders, the exact same id string appears 2-3 times in one
 * document — invalid HTML, and it breaks any `aria-controls`/fragment
 * link relying on ids being unique.
 *
 * Renders header.php + footer.php directly via load_template(…, false)
 * (same technique SingleTemplateTest uses for header.php, to sidestep
 * get_header()/get_footer()'s require_once-across-the-whole-process
 * artifact) against a real nav menu assigned to the `main-menu` location.
 */
class MenuIdDuplicationTest extends WP_UnitTestCase {
    private function createMainMenu(): void {
        $menuId = wp_create_nav_menu('Arena Dedup Menu ' . __METHOD__);
        $this->assertIsInt($menuId);

        wp_update_nav_menu_item($menuId, 0, [
            'menu-item-title'  => 'Item Um',
            'menu-item-url'    => home_url('/item-um/'),
            'menu-item-status' => 'publish',
        ]);
        wp_update_nav_menu_item($menuId, 0, [
            'menu-item-title'  => 'Item Dois',
            'menu-item-url'    => home_url('/item-dois/'),
            'menu-item-status' => 'publish',
        ]);

        set_theme_mod('nav_menu_locations', ['main-menu' => $menuId]);
    }

    /** @return string[] every "menu-item-<digits>" id token found in $html */
    private function extractMenuItemIds(string $html): array {
        preg_match_all('/id="(menu-item-\d+)"/', $html, $matches);
        return $matches[1];
    }

    public function test_header_and_footer_together_have_zero_duplicate_menu_item_ids(): void {
        $this->createMainMenu();
        $this->go_to(home_url('/'));

        ob_start();
        load_template(get_template_directory() . '/header.php', false);
        $header = (string) ob_get_clean();

        ob_start();
        load_template(get_template_directory() . '/footer.php', false);
        $footer = (string) ob_get_clean();

        $ids = $this->extractMenuItemIds($header . $footer);

        $this->assertNotEmpty($ids, 'The menu must actually render ids on at least one pass (the desktop nav) for this test to be meaningful.');

        $duplicates = array_diff_assoc($ids, array_unique($ids));
        $this->assertSame([], array_values($duplicates), 'No menu-item id must repeat across header + footer.');
    }

    /**
     * The desktop nav (the FIRST render) must keep its real ids — only the
     * off-canvas and footer re-renders suppress theirs.
     */
    public function test_desktop_nav_still_gets_real_menu_item_ids(): void {
        $this->createMainMenu();
        $this->go_to(home_url('/'));

        ob_start();
        load_template(get_template_directory() . '/header.php', false);
        $header = (string) ob_get_clean();

        $desktopNav = substr($header, 0, (int) strpos($header, 'offcanvas-menu__header'));

        $this->assertNotEmpty($this->extractMenuItemIds($desktopNav));
    }
}
