<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Attachment/media page template (GAP B). Without this file WordPress
 * falls all the way through its template hierarchy
 * (image.php/video.php/audio.php -> attachment.php -> single.php ->
 * singular.php -> index.php) to whichever of those the theme DOES define —
 * here that meant single.php, which assumes an ordinary post (tags,
 * related-posts query on get_the_category(), etc.) and never rendered the
 * media itself.
 *
 * Reuses the same 2-column shell + breadcrumb pattern as single.php/
 * page.php (template-parts/layout/{content-open,content-close}.php +
 * yoast_breadcrumb() — is_attachment() is also is_singular(), which Yoast
 * already handles). The attachment's title is the page's ONLY <h1>,
 * matching the `.post-header-inner > .post-header-title > h1
 * .single-post-title` markup single.php's own header partial uses, so the
 * same CSS applies without duplicating rules.
 *
 * Image attachments render via wp_get_attachment_image('full', …), which
 * (like the_post_thumbnail(), used elsewhere in this theme) emits explicit
 * width/height from the attachment's own metadata — no extra work needed
 * for "explicit dimensions". Non-image attachments (audio/video/PDF/zip/…)
 * have no meaningful "full size" to render, so they get a plain download/
 * view link via wp_get_attachment_link() instead.
 */
get_header();

get_template_part('template-parts/layout/content-open', null, ['layout' => '2col-right']);

if (function_exists('yoast_breadcrumb')) {
    yoast_breadcrumb('<nav class="arena-breadcrumb">', '</nav>');
}

while (have_posts()):
    the_post();
    $attachmentId = get_the_ID();
    $post = get_post($attachmentId);
    $isImage = $attachmentId && wp_attachment_is_image($attachmentId);
    ?>
    <article id="attachment-<?php the_ID(); ?>" <?php post_class('attachment-content'); ?>>
        <div class="post-header-inner">
            <div class="post-header-title">
                <h1 class="single-post-title"><span class="post-title"><?php the_title(); ?></span></h1>
            </div>
        </div>

        <div class="attachment-media single-featured">
            <?php if ($isImage): ?>
                <?php
                echo wp_get_attachment_image(
                    $attachmentId,
                    'full',
                    false,
                    [
                        'class'    => 'attachment-media__image',
                        'decoding' => 'async',
                        'alt'      => get_the_title($attachmentId),
                    ]
                );
                ?>
            <?php else: ?>
                <p class="attachment-media__link">
                    <?php echo wp_get_attachment_link($attachmentId, 'full', false); ?>
                </p>
            <?php endif; ?>
        </div>

        <?php
        $caption = wp_get_attachment_caption($attachmentId);
        if (is_string($caption) && $caption !== ''):
        ?>
            <p class="attachment-caption wp-caption-text"><?php echo esc_html($caption); ?></p>
        <?php endif; ?>

        <?php
        $description = $post !== null ? trim((string) $post->post_content) : '';
        if ($description !== ''):
        ?>
            <div class="entry-content attachment-description">
                <?php echo apply_filters('the_content', $description); ?>
            </div>
        <?php endif; ?>

        <?php
        $parentId = $post !== null ? (int) $post->post_parent : 0;
        if ($parentId > 0):
            $parentLink = get_permalink($parentId);
            if (is_string($parentLink) && $parentLink !== ''):
        ?>
            <p class="attachment-back-link">
                <a href="<?php echo esc_url($parentLink); ?>">
                    <?php
                    printf(
                        /* translators: %s: title of the post this attachment belongs to. */
                        esc_html__('← Voltar para %s', 'arena'),
                        esc_html(get_the_title($parentId))
                    );
                    ?>
                </a>
            </p>
        <?php
            endif;
        endif;
        ?>
    </article>
    <?php
endwhile;

get_template_part('template-parts/layout/content-close', null, ['layout' => '2col-right']);

get_footer();
