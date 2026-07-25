<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Card com resumo (usado pelo layout `blog`, coluna única). Reconstrução
 * limpa de `.listing-item-blog` observado publicamente: badge de categoria,
 * thumb, título, meta (autor/data/comentários) e `.post-summary`.
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
<article <?php post_class('listing-item listing-item-blog'); ?>>
    <div class="item-inner clearfix">
        <div class="featured clearfix">
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
                        'alt'      => get_the_title($postId),
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
                    <time class="post-published updated" datetime="<?php echo esc_attr(get_the_date('c', $postId)); ?>"><?php echo esc_html(get_the_date('j M, Y', $postId)); ?></time>
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
