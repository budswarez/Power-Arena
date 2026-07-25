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

    public function test_fonts_url_matches_production_link(): void {
        $this->assertSame(
            'https://fonts.googleapis.com/css?family=Barlow:400,500,600,700|Oswald:500,400&display=swap',
            Assets::fontsUrl()
        );
    }

    public function test_resource_hints_adds_google_fonts_preconnects(): void {
        $hints = Assets::resourceHints([], 'preconnect');
        $this->assertSame(
            [
                ['href' => 'https://fonts.googleapis.com'],
                ['href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous'],
            ],
            $hints
        );
    }

    public function test_resource_hints_leaves_other_relation_types_untouched(): void {
        $this->assertSame(['example.com'], Assets::resourceHints(['example.com'], 'dns-prefetch'));
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
     * `Theme::boot()` (chamado uma vez por `functions.php`, já ativo neste
     * processo de testes) desliga globalmente o recurso "auto sizes for
     * lazy-loaded images" do WP — necessário porque `wp_filter_content_tags()`
     * reprocessa TODO o HTML de `the_content` depois que os cards já foram
     * renderizados (via `do_shortcode`) e reintroduziria `sizes=auto,` em
     * qualquer `<img loading=lazy>` mesmo já tendo o valor corrigido, se
     * esse filtro continuasse ligado.
     */
    public function test_auto_sizes_feature_is_disabled_globally(): void {
        $this->assertFalse(apply_filters('wp_img_tag_add_auto_sizes', true));
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
