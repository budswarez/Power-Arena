<?php
declare(strict_types=1);

class LayoutTest extends WP_UnitTestCase {
    public function test_two_col_right_returns_8_4_split(): void {
        $this->assertSame(
            [
                'content' => 'content-column col-8',
                'sidebar' => 'sidebar-column sidebar-column-primary col-4',
            ],
            \Arena\Layout::columnClasses('2col-right')
        );
    }

    public function test_one_col_returns_full_width_content_and_no_sidebar(): void {
        $this->assertSame(
            [
                'content' => 'content-column col-12',
                'sidebar' => '',
            ],
            \Arena\Layout::columnClasses('1col')
        );
    }

    /** Any unrecognized layout key falls back to the shell's default shape. */
    public function test_unknown_layout_falls_back_to_two_col_right(): void {
        $this->assertSame(
            \Arena\Layout::columnClasses('2col-right'),
            \Arena\Layout::columnClasses('bogus-layout')
        );
    }
}
