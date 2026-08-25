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
 *
 * `id="content"` matches the same id template-parts/layout/content-open.php
 * uses on every other template's main landmark — header.php's skip link
 * targets `#content` and must land on a real landmark regardless of which
 * template rendered the page.
 *
 * H1 (whole-branch review, minor finding #10): every OTHER template
 * asserts exactly one `<h1>` (archive-header.php, single/page titles,
 * search/404 headings) — the home previously had none at all, since its
 * entire body is a WPBakery `[vc_row]` tree of `bs-*` listing blocks whose
 * own card/section titles are all `<h2>` by design (a listing page has
 * MANY cards; none of them is uniquely "the" page heading). A
 * visually-hidden `<h1>` carrying the site name is added here rather than
 * promoting a card's own `<h2>` to `<h1>` — the hero mosaic's "first item"
 * is one post among several equally-weighted tiles, not a page title, and
 * conditionally bumping one shared card template's heading level based on
 * which listing happens to render first would coding-couple
 * template-parts/card/hero.php to "am I on the front page's first block"
 * in a way that reads as a page title semantically but isn't one (the
 * post's OWN title belongs to that post, not to the home page). `.screen-
 * reader-text` (already used by the skip link, main.css) keeps this
 * invisible with no visual change.
 */
get_header();
?>
<main id="content" class="main-content page-layout-1-col page-layout-no-sidebar boxed">
    <h1 class="screen-reader-text"><?php echo esc_html(get_bloginfo('name')); ?></h1>
    <?php
    while (have_posts()):
        the_post();
        the_content();
    endwhile;
    ?>
</main>
<?php
get_footer();
