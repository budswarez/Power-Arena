<?php
declare(strict_types=1);

use Arena\Assets;

class AssetsTest extends WP_UnitTestCase {
    private array $manifest = [
        'assets/src/js/main.js'  => ['file' => 'main-abc123.js', 'css' => ['main-def456.css']],
        'assets/src/css/main.css' => ['file' => 'main-def456.css'],
    ];

    public function test_resolve_returns_hashed_entry(): void {
        $r = Assets::resolve($this->manifest, 'assets/src/js/main.js');
        $this->assertSame('main-abc123.js', $r['file']);
        $this->assertSame(['main-def456.css'], $r['css']);
    }

    public function test_resolve_missing_entry_returns_null(): void {
        $this->assertNull(Assets::resolve($this->manifest, 'nope.js'));
    }

    public function test_style_handles_single_css_file(): void {
        $pairs = Assets::styleHandles(['main-def456.css']);
        $this->assertSame(
            [['handle' => 'arena-main', 'file' => 'main-def456.css']],
            $pairs
        );
    }

    public function test_style_handles_multiple_css_files_get_unique_handles_in_order(): void {
        $pairs = Assets::styleHandles(['a.css', 'b.css', 'c.css']);
        $this->assertSame(
            [
                ['handle' => 'arena-main', 'file' => 'a.css'],
                ['handle' => 'arena-main-1', 'file' => 'b.css'],
                ['handle' => 'arena-main-2', 'file' => 'c.css'],
            ],
            $pairs
        );
    }

    public function test_style_handles_empty_array_returns_empty(): void {
        $this->assertSame([], Assets::styleHandles([]));
    }

    /**
     * task-review-fixes-3, FIX 2: fonts are now self-hosted (assets/fonts/,
     * @font-face declarations in main.css) — fontsUrl() no longer points at
     * Google, and nothing enqueues it as a separate stylesheet. Kept as a
     * pure resolver (rather than deleted outright) because Assets::enqueue()
     * still calls it defensively; it now resolves to null, meaning "nothing
     * to enqueue here", which is asserted directly below.
     */
    public function test_fonts_url_no_longer_points_at_google(): void {
        $this->assertNull(Assets::fontsUrl());
    }

    /**
     * The Google-specific preconnect hints are gone now that nothing on the
     * page talks to fonts.googleapis.com/fonts.gstatic.com — resourceHints()
     * must leave the 'preconnect' relation untouched (not add anything),
     * while still passing through whatever hints WordPress/other filters
     * already added.
     */
    public function test_resource_hints_no_longer_adds_google_fonts_preconnects(): void {
        $this->assertSame([], Assets::resourceHints([], 'preconnect'));
        $this->assertSame(['example.com'], Assets::resourceHints(['example.com'], 'preconnect'));
    }

    public function test_resource_hints_leaves_other_relation_types_untouched(): void {
        $this->assertSame(['example.com'], Assets::resourceHints(['example.com'], 'dns-prefetch'));
    }

    /**
     * The ONE face most critical to first paint: Barlow 400 (the body copy
     * weight used above the fold everywhere — see main.css `body{}`, no
     * font-weight override), latin subset (covers pt-BR text; latin-ext is
     * shipped too for completeness but isn't what renders above the fold).
     * preloadFontUrl() is pure/testable so this choice doesn't silently
     * drift as assets/fonts/ file names change.
     */
    public function test_preload_font_url_targets_barlow_400_latin(): void {
        $this->assertSame(
            ARENA_URI . '/assets/fonts/barlow-400-latin.woff2',
            Assets::preloadFontUrl()
        );
    }

    public function test_card_sizes_default_context_is_760_slot(): void {
        $this->assertSame('(max-width: 768px) 100vw, 760px', Assets::cardSizes());
        $this->assertSame('(max-width: 768px) 100vw, 760px', Assets::cardSizes('default'));
        $this->assertSame('(max-width: 768px) 100vw, 760px', Assets::cardSizes('unknown-context'));
    }

    public function test_card_sizes_hero_large_is_two_per_row(): void {
        $this->assertSame('(max-width: 768px) 100vw, 50vw', Assets::cardSizes('hero-large'));
    }

    public function test_card_sizes_hero_compact_is_three_per_row(): void {
        $this->assertSame('(max-width: 768px) 100vw, 33vw', Assets::cardSizes('hero-compact'));
    }

    /** @dataProvider provideContexts */
    public function test_card_sizes_never_starts_with_auto(string $context): void {
        $this->assertStringStartsNotWith('auto', Assets::cardSizes($context));
    }

    /** @return array<int, array{0: string}> */
    public static function provideContexts(): array {
        return [['default'], ['hero-large'], ['hero-compact'], ['garbage']];
    }

    public function test_card_context_detects_compact_hero_class(): void {
        $this->assertSame('hero-compact', Assets::cardContext('attachment-arena-card hero-tile__img hero-tile__img--compact'));
    }

    public function test_card_context_detects_large_hero_class(): void {
        $this->assertSame('hero-large', Assets::cardContext('attachment-arena-card hero-tile__img'));
    }

    public function test_card_context_defaults_for_plain_card_class(): void {
        $this->assertSame('default', Assets::cardContext('attachment-arena-card'));
    }

    public function test_fix_card_image_attributes_replaces_auto_sizes_for_default_context(): void {
        $attr = [
            'class' => 'attachment-arena-card wp-image-42',
            'sizes' => 'auto, (max-width: 760px) 100vw, 760px',
            'src'   => 'https://example.com/image.webp',
        ];

        $fixed = Assets::fixCardImageAttributes($attr, 'arena-card');

        $this->assertSame('(max-width: 768px) 100vw, 760px', $fixed['sizes']);
        $this->assertStringStartsNotWith('auto', $fixed['sizes']);
        $this->assertSame($attr['src'], $fixed['src']);
    }

    public function test_fix_card_image_attributes_replaces_auto_sizes_for_hero_compact_context(): void {
        $attr = [
            'class' => 'attachment-arena-card hero-tile__img hero-tile__img--compact',
            'sizes' => 'auto, (max-width: 760px) 100vw, 760px',
        ];

        $fixed = Assets::fixCardImageAttributes($attr, 'arena-card');

        $this->assertSame('(max-width: 768px) 100vw, 33vw', $fixed['sizes']);
    }

    public function test_fix_card_image_attributes_replaces_auto_sizes_for_hero_large_context(): void {
        $attr = [
            'class' => 'attachment-arena-card hero-tile__img',
            'sizes' => 'auto, (max-width: 760px) 100vw, 760px',
        ];

        $fixed = Assets::fixCardImageAttributes($attr, 'arena-card');

        $this->assertSame('(max-width: 768px) 100vw, 50vw', $fixed['sizes']);
    }

    public function test_fix_card_image_attributes_ignores_other_image_sizes(): void {
        $attr = [
            'class' => 'attachment-full',
            'sizes' => 'auto, 100vw',
        ];

        $fixed = Assets::fixCardImageAttributes($attr, 'full');

        $this->assertSame('auto, 100vw', $fixed['sizes']);
    }

    public function test_fix_card_image_attributes_overrides_generic_wp_default_sizes_too(): void {
        // WP's own default `sizes` for a fixed-crop registered size like
        // `arena-card` is always the same generic 760px value, even for a
        // hero tile that only occupies 33vw/50vw of the viewport — the fix
        // must win over that generic default too, not just over `auto,`.
        $attr = [
            'class' => 'attachment-arena-card hero-tile__img hero-tile__img--compact',
            'sizes' => '(max-width: 760px) 100vw, 760px',
        ];

        $fixed = Assets::fixCardImageAttributes($attr, 'arena-card');

        $this->assertSame('(max-width: 768px) 100vw, 33vw', $fixed['sizes']);
    }

    public function test_fix_card_image_attributes_adds_sizes_when_missing(): void {
        $attr = ['class' => 'attachment-arena-card'];

        $fixed = Assets::fixCardImageAttributes($attr, 'arena-card');

        $this->assertSame('(max-width: 768px) 100vw, 760px', $fixed['sizes']);
    }

    /**
     * Minor finding #12 (whole-branch review): narrowed from a blanket
     * `__return_false` (disabled the WP 6.7 "auto sizes for lazy-loaded
     * images" feature on EVERY request) to only the 2 contexts that
     * actually render a `.hero-tile .img-cont` — the only absolutely
     * positioned image container in this theme, and the actual root cause
     * (`sizes=auto` resolving from LAYOUT width breaks under
     * `position:absolute`; every other `.img-cont`/`.img-holder` is
     * `position:relative`, where `auto` resolves correctly). `Theme::boot()`
     * (called once by functions.php, already active in this test process)
     * wires the narrowed callback.
     */
    public function test_auto_sizes_disabled_on_the_front_page(): void {
        $this->go_to(home_url('/'));
        $this->assertTrue(is_front_page(), 'go_to("/") must land on the front page for this test to be meaningful.');

        $this->assertFalse(apply_filters('wp_img_tag_add_auto_sizes', true));
    }

    public function test_auto_sizes_disabled_on_page_1_of_a_category_archive(): void {
        $catId = $this->factory()->category->create();
        $this->factory()->post->create(['post_status' => 'publish', 'post_category' => [$catId]]);

        $this->go_to(get_term_link($catId));
        $this->assertTrue(is_category(), 'go_to() must land on a category archive for this test to be meaningful.');
        $this->assertFalse(is_paged());

        $this->assertFalse(apply_filters('wp_img_tag_add_auto_sizes', true));
    }

    public function test_auto_sizes_left_enabled_on_an_ordinary_single_post(): void {
        $postId = $this->factory()->post->create(['post_status' => 'publish']);

        $this->go_to(get_permalink($postId));
        $this->assertTrue(is_single());

        $this->assertTrue(apply_filters('wp_img_tag_add_auto_sizes', true));
    }

    public function test_auto_sizes_left_enabled_on_page_2_of_a_category_archive(): void {
        $catId = $this->factory()->category->create();
        for ($i = 0; $i < 3; $i++) {
            $this->factory()->post->create(['post_status' => 'publish', 'post_category' => [$catId]]);
        }
        update_option('posts_per_page', 1);

        $this->go_to(add_query_arg('paged', 2, get_term_link($catId)));
        $this->assertTrue(is_category());
        $this->assertTrue(is_paged(), 'go_to() must land on page 2 for this test to be meaningful.');

        $this->assertTrue(apply_filters('wp_img_tag_add_auto_sizes', true));
    }

    /**
     * FIX 1 (CRITICAL): with an EMPTY manifest (absent/stale on the host —
     * no `npm run build`, or rsync/FTP skipping the dot-directory
     * `.vite/`), the resolver must still hand back a style registration
     * for the `arena-main` handle so `arena-child`'s dependency on it never
     * silently vanishes.
     */
    public function test_resolve_main_assets_empty_manifest_falls_back_to_style_css(): void {
        $resolved = Assets::resolveMainAssets([]);

        $this->assertNull($resolved['js']);
        $this->assertSame(
            [['handle' => 'arena-main', 'file' => null, 'fallback' => true]],
            $resolved['styles']
        );
    }

    public function test_resolve_main_assets_with_manifest_uses_hashed_paths(): void {
        $resolved = Assets::resolveMainAssets($this->manifest);

        $this->assertSame(['handle' => 'arena-main', 'file' => 'main-abc123.js'], $resolved['js']);
        $this->assertSame(
            [['handle' => 'arena-main', 'file' => 'main-def456.css', 'fallback' => false]],
            $resolved['styles']
        );
    }

    /**
     * Integration-level guard: `enqueue()` must register a REAL `arena-main`
     * style handle even when the manifest FILE is missing entirely (the
     * real-world failure mode this fix targets: no `npm run build`, or a
     * deploy tool skipping the `.vite/` dot-directory), so a child theme's
     * `wp_enqueue_style(['arena-main'], ...)` resolves its dependency
     * instead of being silently dropped by WordPress. Temporarily renames
     * the actual manifest file to reproduce that condition, and restores it
     * in a `finally` so the rest of the suite (and any real build output)
     * is never left disturbed.
     */
    /**
     * Minor finding #7 (whole-branch review): the off-canvas submenu
     * toggle's aria-label strings used to be hardcoded pt-BR literals in
     * main.js, bypassing i18n entirely. They're now passed to JS via
     * wp_localize_script() as `window.arenaI18n` instead.
     */
    public function test_enqueue_localizes_offcanvas_i18n_strings_for_arena_main(): void {
        Assets::enqueue();

        $data = wp_scripts()->get_data('arena-main', 'data');

        $this->assertIsString($data);
        $this->assertStringContainsString('arenaI18n', $data);
        $this->assertStringContainsString('expandSubmenuWithLabel', $data);
        $this->assertStringContainsString('expandSubmenu', $data);
    }

    /**
     * Minor finding #11 (whole-branch review): the block editor previously
     * had no editor stylesheet registered at all — the canvas never
     * reflected this theme's real look. `registerEditorStyle()` must call
     * `add_editor_style()` (which itself calls `add_theme_support(
     * 'editor-style')`) so `get_editor_stylesheets()` picks up a real
     * file.
     */
    public function test_register_editor_style_registers_a_real_stylesheet(): void {
        global $editor_styles;
        $editor_styles = [];

        Assets::registerEditorStyle();

        $this->assertTrue(current_theme_supports('editor-style'));
        $this->assertNotEmpty($editor_styles);
        foreach ((array) $editor_styles as $file) {
            $this->assertTrue(
                str_starts_with($file, 'assets/dist/') || $file === 'style.css',
                "Unexpected editor stylesheet path: $file"
            );
        }
    }

    public function test_enqueue_registers_arena_main_style_even_without_manifest_file(): void {
        $path = ARENA_DIR . '/assets/dist/.vite/manifest.json';
        $hadManifest = is_file($path);
        if ($hadManifest) {
            rename($path, $path . '.bak');
        }

        try {
            global $wp_styles;
            $wp_styles = null;

            Assets::enqueue();

            $this->assertTrue(wp_style_is('arena-main', 'registered'));
            $registered = $wp_styles->registered['arena-main'];
            $this->assertStringEndsWith('/style.css', $registered->src);
        } finally {
            if ($hadManifest) {
                rename($path . '.bak', $path);
            }
        }
    }
}
