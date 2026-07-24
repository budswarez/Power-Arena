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
                wp_nav_menu([
                    'theme_location' => 'main-menu',
                    'container'      => false,
                    'menu_class'     => 'footer-menu menu clearfix',
                    'depth'          => 1,
                    'fallback_cb'    => false,
                ]);
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
