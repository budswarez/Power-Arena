<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Shared `.post-meta` partial: author link + published `<time>` + optional
 * comment-count link. Extracted from the identical block that used to be
 * copy-pasted verbatim across card/blog5.php, card/excerpt.php,
 * card/featured.php, card/hero.php and card/text.php (whole-branch review,
 * minor finding #1).
 *
 * @var array<string, mixed> $args {
 *     @type int  $post_id       Required — post the meta belongs to.
 *     @type bool $show_author   Show the author link. Default true.
 *     @type bool $show_comments Show the comments-count link. Default false.
 * }
 */

$postId = (int) ($args['post_id'] ?? 0);
if ($postId <= 0) {
    return;
}

$showAuthor = (bool) ($args['show_author'] ?? true);
$showComments = (bool) ($args['show_comments'] ?? false);

$authorId = $showAuthor ? (int) get_the_author_meta('ID') : 0;
$commentCount = $showComments ? (int) get_comments_number($postId) : 0;
?>
<div class="post-meta">
    <?php if ($authorId > 0): ?>
        <a class="post-author-a" href="<?php echo esc_url(get_author_posts_url($authorId)); ?>">
            <i class="post-author author"><?php echo esc_html(get_the_author()); ?></i>
        </a>
    <?php endif; ?>
    <span class="time">
        <time class="post-published updated" datetime="<?php echo esc_attr(get_the_date('c', $postId)); ?>"><?php echo esc_html(\Arena\Listing\Renderer::articleDate($postId)); ?></time>
    </span>
    <?php if ($showComments): ?>
        <a class="comments" href="<?php echo esc_url(get_comments_link($postId)); ?>">
            <?php echo \Arena\Listing\Renderer::commentIcon(); ?> <?php echo esc_html((string) $commentCount); ?>
        </a>
    <?php endif; ?>
</div>
