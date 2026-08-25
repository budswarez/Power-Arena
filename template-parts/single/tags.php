<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Post tags rendered as chips (styling in main.css targets `.post-tags a`
 * directly — get_the_tag_list() already wraps each tag in its own <a>, so
 * no extra per-tag markup is needed here). Renders NOTHING when the post
 * has no tags: get_the_tag_list() returns '' in that case.
 */

$tagList = get_the_tag_list('', ' ');
if (!is_string($tagList) || $tagList === '') {
    return;
}
?>
<div class="post-tags">
    <?php echo $tagList; ?>
</div>
