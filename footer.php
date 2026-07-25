<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Closes the document: the site footer (repeated main menu + copyright)
 * through wp_footer() and the closing </body></html>. Reproduces the live
 * site's footer shape (`#site-footer.site-footer.full-width`) clean-room —
 * written from the spec, not copied from the legacy Publisher theme's PHP.
 */
?>
    <footer id="site-footer" class="site-footer full-width">
        <div class="footer-inner">
            <nav class="footer-menu-container" aria-label="<?php esc_attr_e('Menu do rodapé', 'arena'); ?>">
                <?php
                /*
                 * `footer-menu` (task-native-settings) when the site owner
                 * assigned a menu there, `main-menu` otherwise — see
                 * Arena\Setup::footerMenuLocation(). When it falls back to
                 * `main-menu`, that same menu is rendered a 3rd time here
                 * (desktop nav + off-canvas panel in header.php already used
                 * it) — same reasoning as the off-canvas render above:
                 * suppress the per-item `id="menu-item-{ID}"` so it doesn't
                 * duplicate the desktop nav's ids a 2nd time over. A
                 * DIFFERENT menu genuinely assigned to `footer-menu` has no
                 * such duplicate-id risk, but suppressing unconditionally is
                 * harmless (footer.php never relied on these ids anyway) and
                 * keeps this call site simple.
                 */
                add_filter('nav_menu_item_id', '__return_empty_string');
                wp_nav_menu([
                    'theme_location' => \Arena\Setup::footerMenuLocation(),
                    'container'      => false,
                    'menu_class'     => 'footer-menu menu clearfix',
                    'depth'          => 1,
                    'fallback_cb'    => false,
                ]);
                remove_filter('nav_menu_item_id', '__return_empty_string');
                ?>
            </nav>

            <div class="copy-footer">
                <?php
                // Was a bare, untranslated string hardcoding the site name
                // literally ("Pichau Arena") outside any translation
                // function instead of reading it from bloginfo('name')
                // (whole-branch review, minor finding #7). Now driven by
                // the "Site Title" option and wrapped for i18n — the
                // visual output for THIS site is unchanged, since its own
                // Site Title option is "Pichau Arena".
                /* translators: %s: site name (bloginfo('name')). */
                $rightsText = sprintf(esc_html__('%s. Todos os Direitos Reservados', 'arena'), esc_html(get_bloginfo('name')));
                ?>
                <p class="footer-copyright">
                    &copy; <?php echo esc_html(gmdate('Y')); ?> - <?php echo $rightsText; ?>
                </p>
            </div>
        </div>
    </footer>

<?php wp_footer(); ?>
</body>
</html>
