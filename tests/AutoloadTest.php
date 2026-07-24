<?php
declare(strict_types=1);

class AutoloadTest extends WP_UnitTestCase {
    public function test_theme_class_autoloads(): void {
        $this->assertTrue(class_exists('Arena\\Theme'), 'Arena\\Theme deveria autoloadar');
    }

    public function test_setup_class_autoloads(): void {
        $this->assertTrue(class_exists('Arena\\Setup'), 'Arena\\Setup deveria autoloadar');
    }
}
