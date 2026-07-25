<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Article byline: author link, published date, comment count. Reuses
 * Arena\Listing\Renderer::commentIcon() — the same inline SVG speech-bubble
 * already used in the card templates' `.post-meta` — so there's exactly one
 * clean-room icon shape shared across the whole theme rather than a 2nd copy
 * living here.
 */

$postId = get_the_ID();
$commentCount = (int) get_comments_number($postId);
?>
<div class="post-meta single-post-meta">
    <span class="post-author-name"><?php echo get_the_author_posts_link(); ?></span>
    <time class="post-published updated" datetime="<?php echo esc_attr(get_the_date('c', $postId)); ?>">
        <?php echo esc_html(get_the_date('', $postId)); ?>
    </time>
    <a class="comments" href="<?php echo esc_url((string) get_comments_link($postId)); ?>">
        <?php echo \Arena\Listing\Renderer::commentIcon(); ?> <?php echo esc_html((string) $commentCount); ?>
    </a>
</div>
