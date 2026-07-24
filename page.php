<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Generic (non-front) page template. Same boxed/no-sidebar wrapper as
 * front-page.php so ordinary WP pages render inside the same layout shell;
 * unlike the home page, a regular page shows its own title above the
 * content.
 */
get_header();
?>
<main id="main-content" class="main-content page-layout-1-col page-layout-no-sidebar boxed">
    <?php
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
    ?>
</main>
<?php
get_footer();
