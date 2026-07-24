<?php
declare(strict_types=1);

use Arena\Compatibility;

class CompatibilityTest extends WP_UnitTestCase {
    public function test_emojis_disabled(): void {
        $this->assertFalse(Compatibility::shouldLoadEmojis());
    }

    /**
     * register() must wire trimCoreBloat() to run on 'init'. We assert the
     * hook is attached without firing 'init' — see
     * test_trim_core_bloat_removes_emoji_action() for why re-firing core
     * hooks inside a test is avoided.
     */
    public function test_register_hooks_trim_core_bloat_to_init(): void {
        Compatibility::register();

        $this->assertNotFalse(has_action('init', [Compatibility::class, 'trimCoreBloat']));
    }

    /**
     * Exercises trimCoreBloat() directly instead of firing 'init'.
     *
     * Re-firing 'init' inside a test also re-runs WordPress core's own
     * init-time registrations (block types, block bindings sources, the
     * font library's default collection), which are not idempotent and can
     * emit `_doing_it_wrong()` notices on a second call — WordPress-version-
     * dependent, unrelated to Compatibility. Calling trimCoreBloat() and
     * checking has_action() directly keeps this test deterministic and
     * scoped to Compatibility's own behavior, independent of what the
     * installed core version does when 'init' fires twice.
     */
    public function test_trim_core_bloat_removes_emoji_action(): void {
        // Establish the precondition core normally sets up at boot,
        // regardless of what the current install already did.
        add_action('wp_head', 'print_emoji_detection_script', 7);
        add_action('wp_print_styles', 'print_emoji_styles');
        $this->assertNotFalse(has_action('wp_head', 'print_emoji_detection_script'));
        $this->assertNotFalse(has_action('wp_print_styles', 'print_emoji_styles'));

        Compatibility::trimCoreBloat();

        $this->assertFalse(has_action('wp_head', 'print_emoji_detection_script'));
        $this->assertFalse(has_action('wp_print_styles', 'print_emoji_styles'));
    }
}
