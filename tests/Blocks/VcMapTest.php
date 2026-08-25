<?php
declare(strict_types=1);

use Arena\Blocks\VcMap;

class VcMapTest extends WP_UnitTestCase {
    private const EXPECTED_BASES = [
        'bs-modern-grid-listing-7',
        'bs-mix-listing-3-1',
        'bs-blog-listing-1',
        'bs-grid-listing-1',
    ];

    public function test_maps_shape(): void {
        $maps = VcMap::maps();

        $this->assertCount(4, $maps);

        $actualBases = [];
        foreach ($maps as $map) {
            $this->assertContains(
                $map['base'],
                self::EXPECTED_BASES,
                'Every map "base" must match one of the 4 registered bs-* shortcode tags.'
            );
            $this->assertSame('Arena', $map['category']);
            $this->assertIsArray($map['params']);
            $this->assertNotEmpty($map['params']);
            $actualBases[] = $map['base'];
        }

        $expectedSorted = self::EXPECTED_BASES;
        sort($expectedSorted);
        sort($actualBases);
        $this->assertSame($expectedSorted, $actualBases, 'The set of 4 bases must exactly equal the 4 expected shortcode tags.');
    }

    public function test_register_is_noop_without_vc(): void {
        // WPBakery ("vc_map") is not present in this test environment; register()
        // must guard against calling vc_map() and must not fatal when the
        // vc_before_init action fires.
        VcMap::register();
        do_action('vc_before_init');

        $this->assertTrue(true);
    }
}
