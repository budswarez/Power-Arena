<?php
declare(strict_types=1);

use Arena\Blocks\Shortcodes;

class ShortcodesTest extends WP_UnitTestCase {
    public function test_register_adds_all_four_bs_shortcodes(): void {
        Shortcodes::register();

        $this->assertTrue(shortcode_exists('bs-modern-grid-listing-7'));
        $this->assertTrue(shortcode_exists('bs-mix-listing-3-1'));
        $this->assertTrue(shortcode_exists('bs-blog-listing-1'));
        $this->assertTrue(shortcode_exists('bs-grid-listing-1'));
    }

    public function test_grid_shortcode_renders_wired_listing_with_post_titles(): void {
        Shortcodes::register();

        $titles = [];
        $postIds = $this->factory()->post->create_many(6);
        foreach ($postIds as $postId) {
            $titles[] = get_the_title($postId);
        }

        $html = do_shortcode('[bs-grid-listing-1 count="3" columns="3"]');

        $this->assertNotSame('', $html);
        $this->assertStringContainsString('bs-listing-grid', $html);

        $found = false;
        foreach ($titles as $title) {
            if (str_contains($html, esc_html($title))) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected the rendered grid listing HTML to contain at least one created post title.');
    }

    public function test_modern_grid_shortcode_renders_wired_listing(): void {
        Shortcodes::register();

        $this->factory()->post->create(['post_title' => 'Arena Shortcode Modern Grid Post']);

        $html = do_shortcode('[bs-modern-grid-listing-7 count="1"]');

        $this->assertStringContainsString('bs-listing-modern-grid', $html);
        $this->assertStringContainsString('Arena Shortcode Modern Grid Post', $html);
    }

    public function test_mix_shortcode_renders_wired_listing(): void {
        Shortcodes::register();

        $this->factory()->post->create(['post_title' => 'Arena Shortcode Mix Post']);

        $html = do_shortcode('[bs-mix-listing-3-1 count="1"]');

        $this->assertStringContainsString('bs-listing-mix', $html);
        $this->assertStringContainsString('Arena Shortcode Mix Post', $html);
    }

    public function test_blog_shortcode_renders_wired_listing_with_excerpt(): void {
        Shortcodes::register();

        $this->factory()->post->create([
            'post_title'   => 'Arena Shortcode Blog Post',
            'post_excerpt' => 'Resumo do shortcode de blog.',
        ]);

        $html = do_shortcode('[bs-blog-listing-1 count="1"]');

        $this->assertStringContainsString('bs-listing-blog', $html);
        $this->assertStringContainsString('Resumo do shortcode de blog.', $html);
    }

    public function test_unknown_attribute_does_not_fatal_and_defaults_apply(): void {
        Shortcodes::register();

        $this->factory()->post->create(['post_title' => 'Arena Shortcode Unknown Attr Post']);

        $html = do_shortcode('[bs-grid-listing-1 totally_unknown_attr="whatever"]');

        $this->assertIsString($html);
        $this->assertNotSame('', $html);
        $this->assertStringNotContainsString('Warning', $html);
        $this->assertStringNotContainsString('Notice', $html);
        $this->assertStringNotContainsString('Fatal error', $html);
        $this->assertStringContainsString('bs-listing-grid', $html);
        // Default columns for grid is 4.
        $this->assertStringContainsString('columns-4', $html);
    }

    public function test_register_is_idempotent_and_safe_to_call_twice(): void {
        Shortcodes::register();
        Shortcodes::register();

        $this->assertTrue(shortcode_exists('bs-grid-listing-1'));

        $this->factory()->post->create(['post_title' => 'Arena Shortcode Double Register Post']);
        $html = do_shortcode('[bs-grid-listing-1 count="1"]');

        $this->assertStringContainsString('Arena Shortcode Double Register Post', $html);
    }
}
