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

    /**
     * BUG 4 (task-uifix): a thumbnail-less post used to leave an empty,
     * non-clickable box where the thumb would be. `placeholderUrl()` must
     * point at the theme's own shipped default image, not an attachment.
     */
    public function test_placeholder_url_points_at_the_theme_asset(): void {
        $this->assertSame(
            get_template_directory_uri() . '/assets/img/placeholder.svg',
            Media::placeholderUrl()
        );
    }

    public function test_placeholder_asset_file_actually_exists_on_disk(): void {
        $this->assertFileExists(get_template_directory() . '/assets/img/placeholder.svg');
    }

    /**
     * The placeholder <img> must carry explicit width/height (same 760x428
     * as the real `arena-card` registered image size) so a thumbnail-less
     * card takes up exactly the same box a real thumbnail would — no
     * layout shift once/if a thumbnail is added later.
     */
    public function test_placeholder_img_has_explicit_dimensions_matching_arena_card_size(): void {
        $html = Media::placeholderImg('Fallback Title');

        $this->assertStringContainsString('width="760"', $html);
        $this->assertStringContainsString('height="428"', $html);
    }

    public function test_placeholder_img_uses_the_given_alt_text(): void {
        $html = Media::placeholderImg('Post Sem Thumbnail');

        $this->assertStringContainsString('alt="Post Sem Thumbnail"', $html);
    }

    public function test_placeholder_img_carries_the_theme_asset_src(): void {
        $html = Media::placeholderImg('Fallback Title');

        $this->assertStringContainsString(
            'src="' . esc_url(Media::placeholderUrl()) . '"',
            $html
        );
    }

    /**
     * Card partials pass through the SAME extra class(es) they'd give a
     * real thumbnail's `<img>` (e.g. `hero-tile__img--compact`) so
     * size-specific CSS applies identically to the placeholder.
     */
    public function test_placeholder_img_includes_the_given_extra_class(): void {
        $html = Media::placeholderImg('Fallback Title', 'attachment-arena-card hero-tile__img--compact');

        $this->assertMatchesRegularExpression(
            '/class="[^"]*attachment-arena-card[^"]*hero-tile__img--compact[^"]*"/',
            $html
        );
    }

    public function test_placeholder_img_never_renders_an_empty_src(): void {
        $html = Media::placeholderImg('Fallback Title');

        $this->assertStringNotContainsString('src=""', $html);
    }
}
