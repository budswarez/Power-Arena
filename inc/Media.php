<?php
declare(strict_types=1);

namespace Arena;

/**
 * Shared `alt` text resolution for every place in the theme that renders an
 * attachment image (the 5 card partials in `template-parts/card/`,
 * `template-parts/single/featured.php` and `attachment.php`).
 *
 * All 7 call sites used to pass `'alt' => get_the_title($postId)` straight
 * into `get_the_post_thumbnail()`/`the_post_thumbnail()`/
 * `wp_get_attachment_image()`. Explicitly passing `alt` in the `$attr`
 * array REPLACES whatever the editor wrote in the attachment's own
 * `_wp_attachment_image_alt` meta — WordPress only falls back to that meta
 * when `alt` is absent from `$attr` entirely. On a newsroom where editors
 * write real alt text, the theme was silently discarding it and
 * substituting the headline, which is also the adjacent link text — so
 * screen readers announced the same string twice per card.
 *
 * `imageAlt()` reproduces the fallback WordPress would have used on its
 * own (the attachment's alt meta), but with the post title as the LAST
 * resort when the meta is empty/missing/whitespace-only — matching this
 * theme's previous (buggy) behaviour for attachments that never had alt
 * text set, while finally honouring real editor-authored alt text when
 * it exists.
 */
final class Media {
    /**
     * @param int    $attachmentId The attachment (thumbnail) post ID. 0/negative is treated as "no attachment".
     * @param string $fallback     Used when the attachment has no non-blank alt meta (typically the post title).
     */
    public static function imageAlt(int $attachmentId, string $fallback): string {
        if ($attachmentId > 0) {
            $alt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
            if (is_string($alt) && trim($alt) !== '') {
                return $alt;
            }
        }

        return $fallback;
    }
}
