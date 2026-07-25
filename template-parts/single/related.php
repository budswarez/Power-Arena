<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Related-posts section for the article page (GAP 1 — a real missing
 * feature: the reference article carries a related-posts block, Arena's
 * single.php previously had none). Called from single.php AFTER the
 * tags/nav partials and BEFORE comments_template().
 *
 * Query: posts sharing the CURRENT post's primary category (get_the_category()[0],
 * same "primary category" convention used by template-parts/card/{featured,blog5}.php),
 * excluding the current post, limited to 4, ordered by date desc. Falls back
 * to a plain recent-posts query (still excluding the current post, no
 * category restriction) when the category query yields fewer than 2 posts —
 * covers posts with no category, or a category too thin to fill the section.
 *
 * Cards reuse template-parts/card/blog5.php (the same "blog-5" card used by
 * archive/search listings) rather than duplicating card markup — this
 * partial only owns the query + the section wrapper/heading.
 *
 * Renders NOTHING (no empty heading/section) when there are zero candidates
 * either way — e.g. a site with only the current post.
 */

$currentId = get_the_ID();
if (!$currentId) {
    return;
}

$categories = get_the_category($currentId);
$primaryCategory = $categories !== [] ? $categories[0] : null;

$relatedQuery = null;

if ($primaryCategory instanceof WP_Term) {
    $categoryQuery = new WP_Query([
        'post_status'         => 'publish',
        'post_type'           => 'post',
        'category__in'        => [$primaryCategory->term_id],
        'post__not_in'        => [$currentId],
        'posts_per_page'      => 4,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    ]);

    // Only trust the category query when it actually fills out the
    // section; a lone match isn't worth showing on its own.
    if ($categoryQuery->post_count >= 2) {
        $relatedQuery = $categoryQuery;
    }
}

if ($relatedQuery === null) {
    $relatedQuery = new WP_Query([
        'post_status'         => 'publish',
        'post_type'           => 'post',
        'post__not_in'        => [$currentId],
        'posts_per_page'      => 4,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    ]);
}

if (!$relatedQuery->have_posts()) {
    return;
}
?>
<section class="related-posts">
    <div class="section-heading-row">
        <div class="section-heading sh-t6 sh-s6"><span class="h-text"><?php esc_html_e('Veja também', 'arena'); ?></span></div>
        <span class="h-flag-continuation" aria-hidden="true"></span>
    </div>
    <div class="listing listing-blog listing-blog-5 clearfix">
        <?php
        while ($relatedQuery->have_posts()):
            $relatedQuery->the_post();
            get_template_part('template-parts/card/blog5', null, ['options' => []]);
        endwhile;
        ?>
    </div>
</section>
<?php
wp_reset_postdata();
