<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Static front page template. The page's own content is a WPBakery
 * `[vc_row]/[vc_column]/[bs-*]` shortcode tree (edited in wp-admin) —
 * `the_content()` already runs the `the_content` filter chain, so WPBakery's
 * `vc_*` shortcodes and Arena's own `bs-*` listing shortcodes
 * (Arena\Blocks\Shortcodes, registered via add_shortcode()) both render
 * without any extra do_shortcode() call here.
 */
get_header();
?>
<main id="main-content" class="main-content page-layout-1-col page-layout-no-sidebar boxed">
    <?php
    while (have_posts()):
        the_post();
        the_content();
    endwhile;
    ?>
</main>
<?php
get_footer();
