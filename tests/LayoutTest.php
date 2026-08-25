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

    /**
     * task-review-fixes-3, FIX 1: '2col-left' (arena_sidebar_position ===
     * 'left') gets the SAME content/sidebar column shape as '2col-right' —
     * only the container class (below) differs, which is what actually
     * flips the visual side via CSS `order` (see main.css).
     */
    public function test_two_col_left_has_same_column_shape_as_two_col_right(): void {
        $this->assertSame(
            \Arena\Layout::columnClasses('2col-right'),
            \Arena\Layout::columnClasses('2col-left')
        );
    }

    // ------------------------------------------------------------------
    // containerClasses() — pure, extracted from template-parts/layout/
    // content-open.php so the container class decision (which used to
    // only look at "has a sidebar or not") is unit-testable directly
    // against the specific layout key, including '2col-left'.
    // ------------------------------------------------------------------

    public function test_container_classes_two_col_right(): void {
        $this->assertSame(
            ['container', 'layout-2-col', 'layout-2-col-1', 'layout-right-sidebar', 'layout-bc-before'],
            \Arena\Layout::containerClasses('2col-right')
        );
    }

    public function test_container_classes_two_col_left(): void {
        $this->assertSame(
            ['container', 'layout-2-col', 'layout-2-col-1', 'layout-left-sidebar', 'layout-bc-before'],
            \Arena\Layout::containerClasses('2col-left')
        );
    }

    public function test_container_classes_one_col(): void {
        $this->assertSame(
            ['container', 'layout-1-col', 'layout-no-sidebar', 'layout-bc-before'],
            \Arena\Layout::containerClasses('1col')
        );
    }

    public function test_container_classes_unknown_falls_back_to_two_col_right(): void {
        $this->assertSame(
            \Arena\Layout::containerClasses('2col-right'),
            \Arena\Layout::containerClasses('bogus-layout')
        );
    }
}
