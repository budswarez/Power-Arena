<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Fallback comments template — loaded by comments_template() (called from
 * single.php) whenever no comments plugin is active. wpDiscuz (the
 * reference's actual comments plugin) hooks the `comments_template` filter
 * to swap in its own template file before this one ever loads, and it ships
 * its own CSS/JS — so this file intentionally uses only WordPress's core
 * wp_list_comments()/comment_form() markup with no wpDiscuz-specific
 * classes, and main.css does not style comment internals beyond basic
 * spacing. Providing this (rather than no comments.php at all) avoids WP
 * core's deprecated "Theme without comments.php" theme-compat fallback,
 * which WP_UnitTestCase flags in tests/SingleTemplateTest.php.
 */

if (post_password_required()) {
    return;
}
?>
<div id="comments" class="comments-area">
    <?php if (have_comments()): ?>
        <h2 class="comments-title"><?php comments_number(); ?></h2>

        <ol class="comment-list">
            <?php wp_list_comments(['style' => 'ol']); ?>
        </ol>

        <?php the_comments_navigation(); ?>
    <?php endif; ?>

    <?php if (comments_open()): ?>
        <?php comment_form(); ?>
    <?php elseif (get_comments_number() > 0): ?>
        <p class="no-comments"><?php esc_html_e('Os comentários estão desativados.', 'arena'); ?></p>
    <?php endif; ?>
</div>
