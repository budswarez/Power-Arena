<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Card "blog-5" (used by the `archive` layout — category/tag/author
 * archives and search results, per the Task 3 brief): reconstruction of
 * the reference `.listing-item-blog-5` — thumb (300px column, see
 * assets/src/css/main.css) floated left, title (Oswald)/meta/excerpt in
 * the text column to its right.
 *
 * Graceful without a thumbnail: the `.featured`/`.img-holder` block is
 * omitted entirely rather than rendered as an empty box or placeholder —
 * same strategy as card/text.php — so a thumbless post degrades to a
 * text-only card, never a broken/empty `<img>`.
 *
 * Category badge (Task 2B polish pass): the reference overlays a
 * `.term-badges.floated`/`data-slug` badge at the BOTTOM-LEFT of every
 * listing card's thumb, not just on the featured tiles (card/hero.php,
 * card/featured.php already do this at the TOP-left) — same markup/colour
 * system, `.listing-item-blog-5 .term-badges.floated` in main.css just
 * repositions it to the bottom for this card.
 *
 * @var array<string, mixed> $args {
 *     @type bool                 $is_first Se este é o 1º card da listagem (LCP).
 *     @type array<string, mixed> $options  Opções vindas de Renderer::buildOptions().
 * }
 */

$postId = get_the_ID();
if (!$postId) {
    return;
}

$options = is_array($args['options'] ?? null) ? $args['options'] : [];
$isFirst = (bool) ($args['is_first'] ?? false);
$showThumb = \Arena\Listing\Renderer::hasUsableThumbnail($postId);
$showExcerpt = ($options['show_excerpt'] ?? true) !== false;

$categories = get_the_category($postId);
$primaryCategory = $categories !== [] ? $categories[0] : null;

$authorId = (int) get_the_author_meta('ID');
$permalink = get_permalink($postId);
$commentCount = (int) get_comments_number($postId);
?>
<article <?php post_class('listing-item listing-item-blog-5'); ?>>
    <div class="item-inner clearfix">
        <?php if ($showThumb): ?>
            <div class="featured">
                <?php if ($primaryCategory): ?>
                    <div class="term-badges floated">
                        <span class="term-badge" data-slug="<?php echo esc_attr($primaryCategory->slug); ?>">
                            <a href="<?php echo esc_url(get_category_link($primaryCategory)); ?>"><?php echo esc_html($primaryCategory->name); ?></a>
                        </span>
                    </div>
                <?php endif; ?>
                <a class="img-holder" href="<?php echo esc_url($permalink); ?>">
                    <?php
                    $imgAttr = [
                        'class'    => 'attachment-arena-card',
                        'decoding' => 'async',
                        'alt'      => get_the_title($postId),
                    ];
                    if ($isFirst) {
                        $imgAttr['fetchpriority'] = 'high';
                        $imgAttr['loading'] = 'eager';
                    }
                    echo get_the_post_thumbnail($postId, 'arena-card', $imgAttr);
                    ?>
                </a>
            </div>
        <?php endif; ?>
        <div class="content-container">
            <h2 class="title">
                <a href="<?php echo esc_url($permalink); ?>" class="post-url post-title"><?php echo esc_html(get_the_title($postId)); ?></a>
            </h2>
            <div class="post-meta">
                <?php if ($authorId > 0): ?>
                    <a class="post-author-a" href="<?php echo esc_url(get_author_posts_url($authorId)); ?>">
                        <i class="post-author author"><?php echo esc_html(get_the_author()); ?></i>
                    </a>
                <?php endif; ?>
                <span class="time">
                    <time class="post-published updated" datetime="<?php echo esc_attr(get_the_date('c', $postId)); ?>"><?php echo esc_html(\Arena\Listing\Renderer::articleDate($postId)); ?></time>
                </span>
                <a class="comments" href="<?php echo esc_url(get_comments_link($postId)); ?>">
                    <?php echo \Arena\Listing\Renderer::commentIcon(); ?> <?php echo esc_html((string) $commentCount); ?>
                </a>
            </div>
            <?php if ($showExcerpt): ?>
                <div class="post-summary"><?php echo wp_kses_post(get_the_excerpt($postId)); ?></div>
            <?php endif; ?>
        </div>
    </div>
</article>
