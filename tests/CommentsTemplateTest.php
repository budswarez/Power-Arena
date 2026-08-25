<?php
declare(strict_types=1);

/**
 * comments.php (GAP D) — fleshed out from a 38-line stub: comment-count
 * heading, wp_list_comments() (avatar/author/date/body, nested threaded
 * replies via the 'ol' style + html5 comment-list support), comment
 * pagination, the comment-reply script (enqueued only when actually
 * needed) and an accessible pt-BR comment_form(). Rendered through the
 * FULL single.php (same load_template() strategy as SingleTemplateTest)
 * since comments.php relies on template-tag state (global $post, the
 * comment loop) that only a real request/go_to() sets up correctly.
 */
class CommentsTemplateTest extends WP_UnitTestCase {
    /**
     * wp_enqueue_script() mutates the global $wp_scripts queue, which
     * WP_UnitTestCase does not reset between tests (unlike hooks/options,
     * which it does snapshot and restore) — without this, 'comment-reply'
     * enqueued by an earlier test in this file would still read as
     * "enqueued" here. Only dequeues this ONE script (rather than
     * replacing the whole $wp_scripts global with a fresh WP_Scripts
     * instance) so core's default script registrations — populated once,
     * on the 'init' action, long before any test runs — stay intact for
     * every other test in the suite.
     */
    public function set_up(): void {
        parent::set_up();
        wp_dequeue_script('comment-reply');
    }

    private function renderSingle(int $postId): string {
        $this->go_to(get_permalink($postId));

        ob_start();
        load_template(get_template_directory() . '/single.php', false);
        return (string) ob_get_clean();
    }

    public function test_comment_count_heading_threaded_list_and_form_render(): void {
        update_option('thread_comments', 1);
        update_option('thread_comments_depth', 5);

        $postId = $this->factory()->post->create(['post_status' => 'publish', 'comment_status' => 'open']);
        $parentCommentId = $this->factory()->comment->create([
            'comment_post_ID'  => $postId,
            'comment_author'   => 'Arena Commenter One',
            'comment_content'  => 'Primeiro comentario de teste.',
            'comment_approved' => 1,
        ]);
        $this->factory()->comment->create([
            'comment_post_ID'  => $postId,
            'comment_parent'   => $parentCommentId,
            'comment_author'   => 'Arena Commenter Two',
            'comment_content'  => 'Resposta de teste.',
            'comment_approved' => 1,
        ]);

        $html = $this->renderSingle($postId);

        $this->assertStringNotContainsString('Fatal error', $html);
        $this->assertStringContainsString('comments-title', $html);
        $this->assertStringContainsString('2 comentários', $html);
        // Both comments, author names and body copy.
        $this->assertStringContainsString('Arena Commenter One', $html);
        $this->assertStringContainsString('Arena Commenter Two', $html);
        $this->assertStringContainsString('Primeiro comentario de teste.', $html);
        $this->assertStringContainsString('Resposta de teste.', $html);
        // Nested (threaded) reply indentation — WP core's own <ol class="children">.
        $this->assertStringContainsString('class="children"', $html);
        // Reply link + accessible pt-BR form.
        $this->assertStringContainsString('comment-reply-link', $html);
        $this->assertStringContainsString('Publicar comentário', $html);
        $this->assertStringContainsString('for="comment"', $html);
        $this->assertStringContainsString('for="author"', $html);
        $this->assertStringContainsString('for="email"', $html);
        // comment-reply core script enqueued because threading is on and comments are open.
        $this->assertTrue(wp_script_is('comment-reply', 'enqueued'));
    }

    public function test_comment_reply_script_not_enqueued_when_threading_disabled(): void {
        update_option('thread_comments', 0);
        $postId = $this->factory()->post->create(['post_status' => 'publish', 'comment_status' => 'open']);

        $this->renderSingle($postId);

        $this->assertFalse(wp_script_is('comment-reply', 'enqueued'));
    }

    /**
     * Minor finding #6 (whole-branch review): comments.php replaces
     * comment_form()'s whole 'fields' array with its own pt-BR
     * author/email/url markup, which used to silently drop core's
     * cookies-consent checkbox (added by core only when
     * `show_comments_cookies_opt_in` is on) instead of merging with it.
     */
    public function test_cookies_consent_field_renders_when_opted_in(): void {
        update_option('show_comments_cookies_opt_in', 1);
        $postId = $this->factory()->post->create(['post_status' => 'publish', 'comment_status' => 'open']);

        $html = $this->renderSingle($postId);

        $this->assertStringContainsString('comment-form-cookies-consent', $html);
        $this->assertStringContainsString('wp-comment-cookies-consent', $html);
    }

    public function test_cookies_consent_field_absent_when_opted_out(): void {
        update_option('show_comments_cookies_opt_in', 0);
        $postId = $this->factory()->post->create(['post_status' => 'publish', 'comment_status' => 'open']);

        $html = $this->renderSingle($postId);

        $this->assertStringNotContainsString('comment-form-cookies-consent', $html);
    }

    public function test_closed_comments_with_existing_comments_shows_disabled_message_not_form(): void {
        $postId = $this->factory()->post->create(['post_status' => 'publish', 'comment_status' => 'closed']);
        $this->factory()->comment->create([
            'comment_post_ID'  => $postId,
            'comment_approved' => 1,
        ]);

        $html = $this->renderSingle($postId);

        $this->assertStringContainsString('Os comentários estão desativados.', $html);
        $this->assertStringNotContainsString('id="commentform"', $html);
    }
}
