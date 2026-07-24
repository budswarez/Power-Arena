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
}
