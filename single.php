<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Single article template — the highest-traffic page of the site. Reuses
 * the 2-column shell built in Fatia 2B Task 1
 * (template-parts/layout/{content-open,content-close}.php +
 * Arena\Layout::columnClasses()) and delegates the article body to a set of
 * small partials in template-parts/single/, mirroring the reference's
 * measured markup order inside <article>: featured image, header/H1, meta,
 * entry-content (+ wp_link_pages() for paginated posts), tags, then
 * previous/next navigation. Comments render via comments_template() alone —
 * wpDiscuz (when active) ships its own CSS, so nothing here styles its
 * internals, and the theme carries no comments.php of its own, so a locally
 * missing comments plugin degrades to WordPress's own default comment form
 * rather than fataling.
 *
 * Breadcrumbs render BEFORE the article, immediately inside the shell's
 * content column (`layout-bc-before`, see content-open.php's own docblock) —
 * Yoast owns the breadcrumb trail's markup/schema, so no custom fallback is
 * rendered here to avoid duplicating schema output when Yoast isn't active.
 */
get_header();

get_template_part('template-parts/layout/content-open', null, ['layout' => '2col-right']);

if (function_exists('yoast_breadcrumb')) {
    yoast_breadcrumb('<nav class="arena-breadcrumb">', '</nav>');
}

while (have_posts()):
    the_post();
    ?>
    <div class="single-container">
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <?php get_template_part('template-parts/single/featured'); ?>
            <?php get_template_part('template-parts/single/header'); ?>
            <?php get_template_part('template-parts/single/meta'); ?>

            <div class="entry-content clearfix single-post-content">
                <?php
                the_content();
                wp_link_pages([
                    'before' => '<div class="page-links">' . esc_html__('Páginas:', 'arena'),
                    'after'  => '</div>',
                ]);
                ?>
            </div>

            <?php get_template_part('template-parts/single/tags'); ?>
            <?php get_template_part('template-parts/single/nav'); ?>
        </article>
    </div>
    <?php
    if (comments_open() || (int) get_comments_number() > 0) {
        comments_template();
    }
endwhile;

get_template_part('template-parts/layout/content-close', null, ['layout' => '2col-right']);

get_footer();
