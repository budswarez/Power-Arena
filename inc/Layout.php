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
}
