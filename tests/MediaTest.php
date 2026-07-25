<?php
declare(strict_types=1);

use Arena\Media;

class MediaTest extends WP_UnitTestCase {
    private function createAttachment(): int {
        $attachmentId = $this->factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/canola.jpg'
        );
        $this->assertIsInt($attachmentId);

        return $attachmentId;
    }

    public function test_returns_editor_authored_alt_meta_when_present(): void {
        $attachmentId = $this->createAttachment();
        update_post_meta($attachmentId, '_wp_attachment_image_alt', 'texto alternativo real');

        $this->assertSame(
            'texto alternativo real',
            Media::imageAlt($attachmentId, 'Fallback Title')
        );
    }

    public function test_falls_back_to_given_fallback_when_alt_meta_missing(): void {
        $attachmentId = $this->createAttachment();

        $this->assertSame('Fallback Title', Media::imageAlt($attachmentId, 'Fallback Title'));
    }

    public function test_falls_back_when_alt_meta_is_whitespace_only(): void {
        $attachmentId = $this->createAttachment();
        update_post_meta($attachmentId, '_wp_attachment_image_alt', "   \t  ");

        $this->assertSame('Fallback Title', Media::imageAlt($attachmentId, 'Fallback Title'));
    }

    public function test_falls_back_when_alt_meta_is_empty_string(): void {
        $attachmentId = $this->createAttachment();
        update_post_meta($attachmentId, '_wp_attachment_image_alt', '');

        $this->assertSame('Fallback Title', Media::imageAlt($attachmentId, 'Fallback Title'));
    }

    public function test_zero_attachment_id_returns_fallback(): void {
        $this->assertSame('Fallback Title', Media::imageAlt(0, 'Fallback Title'));
    }

    public function test_preserves_alt_meta_with_internal_whitespace(): void {
        $attachmentId = $this->createAttachment();
        update_post_meta($attachmentId, '_wp_attachment_image_alt', 'Placa de video RTX 4090');

        $this->assertSame('Placa de video RTX 4090', Media::imageAlt($attachmentId, 'Fallback Title'));
    }
}
