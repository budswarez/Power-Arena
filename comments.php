<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Comments template — loaded by comments_template() (called from
 * single.php) whenever no comments plugin is active. wpDiscuz (the
 * reference's actual comments plugin) hooks the `comments_template` filter
 * to swap in its own template file before this one ever loads, and it ships
 * its own CSS/JS — so this file intentionally uses only WordPress core's
 * wp_list_comments()/comment_form() markup (via the 'comment-list'/
 * 'comment-form' HTML5 theme supports declared in Arena\Setup::themeSupports())
 * with no wpDiscuz-specific classes, and main.css only styles THIS core
 * markup shape, never wpDiscuz's own internals. Providing this (rather than
 * no comments.php at all) also avoids WP core's deprecated "Theme without
 * comments.php" theme-compat fallback, which WP_UnitTestCase flags in
 * tests/SingleTemplateTest.php.
 *
 * GAP D: this used to be a 38-line stub (bare wp_list_comments()/
 * comment_form() calls, no heading count, no reply script, no pt-BR labels
 * on the form fields). wpDiscuz takes over in production, but the sandbox
 * here runs without it — this is genuinely what renders when a visitor
 * comments locally, so it needs to hold up on its own: a comment-count
 * heading, threaded replies (comment-reply core script, enqueued only when
 * actually needed), comment pagination, and an accessible pt-BR comment
 * form.
 */

if (post_password_required()) {
    return;
}

// Threaded replies need WP core's `comment-reply` script (adds the
// "Reply" link's click handler that moves the form under the comment being
// replied to) — only worth loading when there's somewhere for a reply to
// go: comments must be open AND threading enabled (get_option('thread_comments')).
if (comments_open() && get_option('thread_comments')) {
    wp_enqueue_script('comment-reply');
}
?>
<div id="comments" class="comments-area">
    <?php if (have_comments()): ?>
        <h2 class="comments-title">
            <?php
            $commentsCount = (int) get_comments_number();
            printf(
                esc_html(
                    /* translators: %s: number of comments, formatted for the current locale. */
                    _n('%s comentário', '%s comentários', $commentsCount, 'arena')
                ),
                esc_html(number_format_i18n($commentsCount))
            );
            ?>
        </h2>

        <ol class="comment-list">
            <?php
            wp_list_comments([
                'style'       => 'ol',
                'avatar_size' => 48,
                'short_ping'  => true,
            ]);
            ?>
        </ol>

        <?php the_comments_navigation(); ?>
    <?php endif; ?>

    <?php if (comments_open()): ?>
        <?php
        // Pre-fills the author/email/url fields for a returning commenter
        // (cookie-remembered), same data comment_form() itself would use
        // internally — needed here explicitly because the custom 'fields'
        // markup below is built before comment_form() runs.
        $commenter = wp_get_current_commenter();

        $fields = [
            'author' => '<p class="comment-form-author">'
                . '<label for="author">' . esc_html__('Nome', 'arena') . ' <span class="required">*</span></label>'
                . '<input id="author" name="author" type="text" value="' . esc_attr((string) ($commenter['comment_author'] ?? '')) . '" size="30" maxlength="245" required="required" />'
                . '</p>',
            'email' => '<p class="comment-form-email">'
                . '<label for="email">' . esc_html__('E-mail', 'arena') . ' <span class="required">*</span></label>'
                . '<input id="email" name="email" type="email" value="' . esc_attr((string) ($commenter['comment_author_email'] ?? '')) . '" size="30" maxlength="100" required="required" />'
                . '</p>',
            'url' => '<p class="comment-form-url">'
                . '<label for="url">' . esc_html__('Site', 'arena') . '</label>'
                . '<input id="url" name="url" type="url" value="' . esc_attr((string) ($commenter['comment_author_url'] ?? '')) . '" size="30" maxlength="200" />'
                . '</p>',
        ];

        // Merge in core's own cookies-consent checkbox instead of silently
        // dropping it (whole-branch review, minor finding #6): replacing
        // `comment_form()`'s whole 'fields' array (as this template always
        // has, for the pt-BR labels/markup above) also silently drops
        // whatever OTHER fields core would have added to its own default
        // array — including this one, which core only adds when the site
        // has opted into the GDPR cookies-consent checkbox
        // (`show_comments_cookies_opt_in`). A privacy-consent control
        // should not regress out of the form as a side effect of
        // localizing the other three fields. Mirrors core's own
        // conditional + markup (wp-includes/comment-template.php).
        if (has_action('set_comment_cookies', 'wp_set_comment_cookies') && get_option('show_comments_cookies_opt_in')) {
            $consentChecked = empty($commenter['comment_author_email']) ? '' : ' checked="checked"';
            $fields['cookies'] = '<p class="comment-form-cookies-consent">'
                . '<input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"' . $consentChecked . ' />'
                . '<label for="wp-comment-cookies-consent">' . esc_html__('Salvar meu nome, e-mail e site neste navegador para a próxima vez que eu comentar.', 'arena') . '</label>'
                . '</p>';
        }

        comment_form([
            'title_reply'          => esc_html__('Deixe um comentário', 'arena'),
            /* translators: %s: author of the comment being replied to. */
            'title_reply_to'       => esc_html__('Deixe uma resposta para %s', 'arena'),
            'cancel_reply_link'    => esc_html__('Cancelar resposta', 'arena'),
            'label_submit'         => esc_html__('Publicar comentário', 'arena'),
            'comment_field'        => '<p class="comment-form-comment">'
                . '<label for="comment">' . esc_html__('Comentário', 'arena') . ' <span class="required">*</span></label>'
                . '<textarea id="comment" name="comment" cols="45" rows="6" maxlength="65525" required="required"></textarea>'
                . '</p>',
            'fields'               => $fields,
            'class_submit'         => 'submit comment-submit-button',
            'comment_notes_before' => '<p class="comment-notes">' . esc_html__('Seu e-mail não será publicado.', 'arena') . '</p>',
            'comment_notes_after'  => '',
        ]);
        ?>
    <?php elseif ((int) get_comments_number() > 0): ?>
        <p class="no-comments"><?php esc_html_e('Os comentários estão desativados.', 'arena'); ?></p>
    <?php endif; ?>
</div>
