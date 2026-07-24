<?php
declare(strict_types=1);

use Arena\Compatibility;

class CompatibilityTest extends WP_UnitTestCase {
    public function test_emojis_disabled(): void {
        $this->assertFalse(Compatibility::shouldLoadEmojis());
    }

    /**
     * Re-firing 'init' inside a test also re-runs WordPress core's own
     * init-time registrations (block types, block bindings sources, the
     * font library's default collection). Core flags each of those as
     * incorrect usage when called a second time in the same request —
     * this is an artifact of the test manually re-triggering the hook, not
     * a bug in Compatibility: in normal execution 'init' fires exactly
     * once. We tell WP_UnitTestCase to expect these three notices so the
     * test isn't failed by unrelated core noise.
     */
    public function test_register_removes_emoji_action(): void {
        $this->setExpectedIncorrectUsage('WP_Font_Library::register_font_collection');
        $this->setExpectedIncorrectUsage('WP_Block_Bindings_Registry::register');
        $this->setExpectedIncorrectUsage('WP_Block_Type_Registry::register');

        Compatibility::register();
        do_action('init');
        $this->assertFalse(has_action('wp_head', 'print_emoji_detection_script'));
    }
}
