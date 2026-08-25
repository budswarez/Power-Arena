<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Featured image for the single article — the page's LCP element. Renders
 * NOTHING when the post has no usable thumbnail: reuses
 * Arena\Listing\Renderer::hasUsableThumbnail() (the same guard the card
 * templates use) so a stale `_thumbnail_id` pointing at a file that never
 * made it into wp-content/uploads doesn't render a broken <img> here
 * either.
 *
 * `the_post_thumbnail('full', …)` already emits explicit `width`/`height`
 * attributes from the attachment's own metadata (core behavior since
 * WP 5.5) — no extra work needed to satisfy the "explicit dimensions"
 * requirement beyond passing `fetchpriority`/`decoding` through. The
 * attachment excerpt is the WordPress caption for the featured image; when
 * it is not blank, it is rendered immediately after the image.
 */

$postId = get_the_ID();
if (!$postId || !\Arena\Listing\Renderer::hasUsableThumbnail($postId)) {
    return;
}
?>
<div class="single-featured">
    <?php
    // markAboveTheFold acrescenta `loading="eager"` e `skip-lazy`: esta é a
    // imagem de destaque da matéria, quase sempre o elemento LCP da página, e
    // `fetchpriority` sozinho não impede o lazy-loader do EWWW de trocar o
    // `src` por um placeholder. Ver Arena\Media::markAboveTheFold().
    the_post_thumbnail('full', \Arena\Media::markAboveTheFold([
        'decoding' => 'async',
        'alt'      => \Arena\Media::imageAlt((int) get_post_thumbnail_id($postId), get_the_title($postId)),
    ]));

    $caption = wp_get_attachment_caption((int) get_post_thumbnail_id($postId));
    if (is_string($caption) && trim($caption) !== ''):
        ?>
        <p class="single-featured-caption wp-caption-text"><?php echo wp_kses_post($caption); ?></p>
        <?php
    endif;
    ?>
</div>
