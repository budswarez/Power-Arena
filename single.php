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
 * previous/next navigation. A related-posts section
 * (template-parts/single/related.php) follows the closing </article>, still
 * inside `.single-container`, before comments — see that partial's own
 * docblock for its query/fallback shape. Comments render via
 * comments_template() alone —
 * wpDiscuz (when active) ships its own CSS, so nothing here styles its
 * internals, and the theme carries no comments.php of its own, so a locally
 * missing comments plugin degrades to WordPress's own default comment form
 * rather than fataling.
 *
 * Breadcrumbs render ABOVE the 2-column row entirely (task-uifix BUG 5) —
 * a full-width band directly inside `<main>`, spanning the whole boxed
 * 1200px column, not squeezed into the 8/12 content column beside an empty
 * sidebar column. Rendered between template-parts/layout/content-open.php
 * (opens just `<main>`) and template-parts/layout/content-row-open.php
 * (opens the `.container`/`.content-column` row) — see both partials' own
 * docblocks. Yoast owns the breadcrumb trail's markup/schema, so no custom
 * fallback is rendered here to avoid duplicating schema output when Yoast
 * isn't active.
 */
get_header();

get_template_part('template-parts/layout/content-open');

/*
 * Breadcrumb do plugin de SEO ativo, resolvido em um só lugar
 * (Arena\Seo::breadcrumb() — Rank Math, Yoast ou SEOPress). Antes cada
 * template chamava `yoast_breadcrumb()` direto, e a trilha desapareceu do site
 * inteiro no dia em que o Yoast foi substituído pelo Rank Math: a função
 * deixou de existir e o `function_exists` simplesmente pulava, sem erro.
 */
\Arena\Seo::breadcrumb();

get_template_part('template-parts/layout/content-row-open', null, ['layout' => \Arena\Options::sidebarLayout()]);

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

        <?php get_template_part('template-parts/single/related'); ?>
    </div>
    <?php
    if (comments_open() || (int) get_comments_number() > 0) {
        comments_template();
    }
endwhile;

get_template_part('template-parts/layout/content-close', null, ['layout' => \Arena\Options::sidebarLayout()]);

get_footer();
