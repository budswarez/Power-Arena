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
                 * `main-menu` is rendered a 3rd time here (desktop nav +
                 * off-canvas panel in header.php already used it). Same
                 * reasoning as the off-canvas render above: suppress the
                 * per-item `id="menu-item-{ID}"` so it doesn't duplicate the
                 * desktop nav's ids a 2nd time over.
                 */
                add_filter('nav_menu_item_id', '__return_empty_string');
                wp_nav_menu([
                    'theme_location' => 'main-menu',
                    'container'      => false,
                    'menu_class'     => 'footer-menu menu clearfix',
                    'depth'          => 1,
                    'fallback_cb'    => false,
                ]);
                remove_filter('nav_menu_item_id', '__return_empty_string');
                ?>
            </nav>

            <div class="copy-footer">
                <p class="footer-copyright">
                    &copy; <?php echo esc_html(gmdate('Y')); ?> - Pichau Arena. Todos os Direitos Reservados
                </p>
            </div>
        </div>
    </footer>

<?php wp_footer(); ?>
</body>
</html>
