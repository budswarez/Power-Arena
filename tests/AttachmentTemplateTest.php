<?php
declare(strict_types=1);

/**
 * attachment.php (GAP B) — media/attachment pages previously fell through
 * the template hierarchy to single.php, which assumes an ordinary post.
 * Same full-template render strategy as SingleTemplateTest/PageTemplateTest:
 * go_to() a real permalink, load_template() the FULL file.
 */
class AttachmentTemplateTest extends WP_UnitTestCase {
    public function set_up(): void {
        parent::set_up();
        $this->set_permalink_structure('/%postname%/');
    }

    private function renderAttachment(int $attachmentId): string {
        $this->go_to((string) get_permalink($attachmentId));
        $this->assertTrue(is_attachment(), 'go_to() must land on the attachment query for this test to be meaningful.');

        ob_start();
        load_template(get_template_directory() . '/attachment.php', false);
        return (string) ob_get_clean();
    }

    public function test_image_attachment_renders_media_with_dimensions_shell_caption_and_parent_link(): void {
        $parentId = $this->factory()->post->create([
            'post_title'  => 'Arena Attachment Parent Post',
            'post_status' => 'publish',
        ]);
        $attachmentId = $this->factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/canola.jpg',
            $parentId
        );
        wp_update_post([
            'ID'           => $attachmentId,
            'post_title'   => 'Arena Attachment Image',
            'post_excerpt' => 'Legenda de teste do anexo',
            'post_content' => 'Descricao completa do anexo de teste.',
        ]);

        $html = $this->renderAttachment($attachmentId);

        $this->assertNotSame('', $html);
        $this->assertStringNotContainsString('Fatal error', $html);
        // Exactly one H1 on the page — the attachment title.
        $this->assertSame(1, preg_match_all('/<h1[ >]/', $html));
        $this->assertStringContainsString('Arena Attachment Image', $html);
        // 2-column shell present.
        $this->assertStringContainsString('content-column', $html);
        $this->assertStringContainsString('sidebar-column', $html);
        // Image renders with explicit dimensions (wp_get_attachment_image('full', …)).
        $this->assertMatchesRegularExpression('/<img[^>]*\bwidth="\d+"/', $html);
        $this->assertMatchesRegularExpression('/<img[^>]*\bheight="\d+"/', $html);
        // Caption + description.
        $this->assertStringContainsString('Legenda de teste do anexo', $html);
        $this->assertStringContainsString('Descricao completa do anexo de teste', $html);
        // Link back to the parent post.
        $this->assertStringContainsString(get_permalink($parentId), $html);
        $this->assertStringContainsString('Arena Attachment Parent Post', $html);
    }

    public function test_non_image_attachment_uses_link_fallback_and_no_parent_link_when_orphaned(): void {
        $attachmentId = $this->factory()->attachment->create_object(
            'arena-test-document.pdf',
            0,
            [
                'post_mime_type' => 'application/pdf',
                'post_title'     => 'Arena Attachment Document',
                'post_status'    => 'inherit',
            ]
        );

        $html = $this->renderAttachment($attachmentId);

        $this->assertNotSame('', $html);
        $this->assertStringNotContainsString('Fatal error', $html);
        $this->assertStringContainsString('Arena Attachment Document', $html);
        $this->assertStringContainsString('attachment-media__link', $html);
        $this->assertStringNotContainsString('<img', $html);
        // No parent (post_parent === 0): no back-link block.
        $this->assertStringNotContainsString('attachment-back-link', $html);
    }
}
