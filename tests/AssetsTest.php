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
}
