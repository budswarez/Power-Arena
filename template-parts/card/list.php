<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Unified "list" card — thumb (or thumbless text-only) + title (Oswald) +
 * `.post-meta` + `.post-summary` excerpt. Reconstruction of the reference
 * `.listing-item-blog-5` / `.listing-item-blog` markup.
 *
 * Replaces the former card/blog5.php + card/excerpt.php, which were ~95%
 * identical (whole-branch review, minor finding #1): the only real
 * differences were the wrapper class and whether the category badge sits
 * inside or outside the thumb guard, both captured here via `$variant`:
 *
 * - `archive` (default): used by category/tag/author archives, search
 *   results and the related-posts section. Wrapper class
 *   `listing-item-blog-5`. The `.featured` block — badge included — is
 *   omitted entirely when the post has no thumbnail (same as
 *   card/text.php): a category without a thumbnail never shows a badge
 *   here, matching the previous card/blog5.php behaviour exactly.
 * - `blog`: used by the single-column `blog` layout. Wrapper class
 *   `listing-item-blog`. The badge is shown whenever there is a primary
 *   category, independent of the thumbnail. Previously (card/excerpt.php)
 *   the `.featured clearfix` wrapper was emitted unconditionally, leaving
 *   an empty `<div class="featured clearfix"></div>` when a post had
 *   neither a thumbnail nor a category — fixed here by omitting the
 *   wrapper in that case too.
 *
 * @var array<string, mixed> $args {
 *     @type bool                 $is_first Se este é o 1º card da listagem (LCP).
 *     @type array<string, mixed> $options  Opções vindas de Renderer::buildOptions().
 *     @type string               $variant  'archive' (default) or 'blog'.
 * }
 */

$postId = get_the_ID();
if (!$postId) {
    return;
}

$options = is_array($args['options'] ?? null) ? $args['options'] : [];
$isFirst = (bool) ($args['is_first'] ?? false);
$variant = ($args['variant'] ?? 'archive') === 'blog' ? 'blog' : 'archive';
$showThumb = \Arena\Listing\Renderer::hasUsableThumbnail($postId);
$showExcerpt = ($options['show_excerpt'] ?? true) !== false;

$categories = get_the_category($postId);
$primaryCategory = $categories !== [] ? $categories[0] : null;

$permalink = get_permalink($postId);

$wrapperClass = $variant === 'blog' ? 'listing-item-blog' : 'listing-item-blog-5';
$featuredClass = $variant === 'blog' ? 'featured clearfix' : 'featured';

// archive: badge only ever appears alongside a thumbnail (matches the old
// card/blog5.php, where the badge lived inside the thumb-only wrapper).
// blog: badge appears independently of the thumbnail (matches the old
// card/excerpt.php), but — unlike before — the wrapper itself is skipped
// when there is neither a thumbnail nor a category, instead of emitting an
// empty `.featured` div.
$showFeaturedBlock = $variant === 'blog' ? ($showThumb || $primaryCategory !== null) : $showThumb;
?>
<article <?php post_class('listing-item ' . $wrapperClass); ?>>
    <div class="item-inner clearfix">
        <?php if ($showFeaturedBlock): ?>
            <div class="<?php echo esc_attr($featuredClass); ?>">
                <?php if ($primaryCategory): ?>
                    <div class="term-badges floated">
                        <span class="term-badge" data-slug="<?php echo esc_attr($primaryCategory->slug); ?>">
                            <a href="<?php echo esc_url(get_category_link($primaryCategory)); ?>"><?php echo esc_html($primaryCategory->name); ?></a>
                        </span>
                    </div>
                <?php endif; ?>
                <?php if ($showThumb): ?>
                    <a class="img-holder" href="<?php echo esc_url($permalink); ?>">
                        <?php
                        $imgAttr = [
                            'class'    => 'attachment-arena-card',
                            'decoding' => 'async',
                            'alt'      => \Arena\Media::imageAlt((int) get_post_thumbnail_id($postId), get_the_title($postId)),
                        ];
                        if ($isFirst) {
                            $imgAttr['fetchpriority'] = 'high';
                            $imgAttr['loading'] = 'eager';
                        }
                        echo get_the_post_thumbnail($postId, 'arena-card', $imgAttr);
                        ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="content-container">
            <h2 class="title">
                <a href="<?php echo esc_url($permalink); ?>" class="post-url post-title"><?php echo esc_html(get_the_title($postId)); ?></a>
            </h2>
            <?php get_template_part('template-parts/card/meta', null, ['post_id' => $postId, 'show_comments' => true]); ?>
            <?php if ($showExcerpt): ?>
                <div class="post-summary"><?php echo wp_kses_post(get_the_excerpt($postId)); ?></div>
            <?php endif; ?>
        </div>
    </div>
</article>
