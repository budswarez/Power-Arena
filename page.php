<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Generic (non-front) page template. GAP 3: the reference's ordinary PAGES
 * use the SAME 2-column right-sidebar shell as posts
 * (`page-layout-2-col page-layout-2-col-right`) — not the front page's own
 * full-width boxed wrapper, which remains front-page.php's alone (a static
 * front page also satisfies is_page(), so Arena\Setup::shellBodyClasses()
 * explicitly excludes is_front_page() to keep the home unaffected by this
 * change; see tests/SetupTest.php).
 *
 * Reuses the same shell partials as single.php
 * (template-parts/layout/{content-open,content-close}.php +
 * Arena\Layout::columnClasses()) and renders breadcrumbs the same way
 * (before the content, inside the shell's content column) — unlike the
 * home page, a regular page also shows its own title above the content.
 */
get_header();

get_template_part('template-parts/layout/content-open', null, ['layout' => \Arena\Options::sidebarLayout()]);

if (function_exists('yoast_breadcrumb')) {
    yoast_breadcrumb('<nav class="arena-breadcrumb">', '</nav>');
}

while (have_posts()):
    the_post();
    ?>
    <article <?php post_class('page-content'); ?>>
        <?php if (get_the_title() !== ''): ?>
            <h1 class="entry-title page-title"><?php the_title(); ?></h1>
        <?php endif; ?>
        <div class="entry-content">
            <?php the_content(); ?>
        </div>
    </article>
    <?php
endwhile;

get_template_part('template-parts/layout/content-close', null, ['layout' => \Arena\Options::sidebarLayout()]);

get_footer();
