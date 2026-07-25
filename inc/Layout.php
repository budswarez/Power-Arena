<?php
declare(strict_types=1);

namespace Arena;

final class Layout {
    /**
     * Pure helper mapping a layout key to the column CSS classes consumed by
     * template-parts/layout/{content-open,content-close}.php and
     * sidebar.php. No WordPress calls — safe to unit test directly
     * (tests/LayoutTest.php).
     *
     * '2col-right': the shell shared by single/archive/search — measured on
     * the reference as an 8/12 content column + 4/12 right sidebar
     * (`.col-sm-8.content-column` / `.col-sm-4.sidebar-column
     * .sidebar-column-primary`). Rendered here with this theme's own
     * `col-8`/`col-4` grid classes (see the 2-column grid in main.css),
     * not Bootstrap's `col-sm-*`.
     * '1col': no sidebar — the content column spans the full 12/12 width.
     * Anything else (including an unrecognized key) falls back to
     * '2col-right', the shell's default shape.
     *
     * @return array{content: string, sidebar: string}
     */
    public static function columnClasses(string $layout): array {
        if ($layout === '1col') {
            return [
                'content' => 'content-column col-12',
                'sidebar' => '',
            ];
        }

        return [
            'content' => 'content-column col-8',
            'sidebar' => 'sidebar-column sidebar-column-primary col-4',
        ];
    }

    /**
     * Pure helper (task-review-fixes-3, FIX 1) mapping a layout key to the
     * `.container` classes template-parts/layout/content-open.php renders.
     * Extracted out of that partial (which used to derive these classes
     * inline from a plain "has a sidebar or not" boolean) so a THIRD shape
     * — '2col-left' — is representable and unit-testable: it renders the
     * SAME 8/12 + 4/12 column split as '2col-right' (see columnClasses()
     * above), but tags the container `layout-left-sidebar` instead of
     * `layout-right-sidebar`. main.css uses that class to flip the flex
     * `order` of `.sidebar-column` so it renders visually before the
     * content column, without changing the DOM order the two partials
     * emit it in.
     *
     * @return string[]
     */
    public static function containerClasses(string $layout): array {
        if ($layout === '1col') {
            return ['container', 'layout-1-col', 'layout-no-sidebar', 'layout-bc-before'];
        }

        if ($layout === '2col-left') {
            return ['container', 'layout-2-col', 'layout-2-col-1', 'layout-left-sidebar', 'layout-bc-before'];
        }

        return ['container', 'layout-2-col', 'layout-2-col-1', 'layout-right-sidebar', 'layout-bc-before'];
    }
}
