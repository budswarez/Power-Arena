<?php
declare(strict_types=1);

namespace Arena;

/**
 * Pagination shared by every archive-style listing (category/tag/author
 * archives and search results — see the Task 3 brief): wraps WordPress's
 * own `paginate_links()` in `<nav class="arena-pagination">`, styled with
 * the accent color on the current page/hover in assets/src/css/main.css.
 *
 * Takes an explicit $totalPages/$currentPage instead of reading the global
 * $wp_query, so it stays independently unit-testable — simulating a real
 * paginated request (rewrite rules, the 'paged' query var, a fake
 * WP_Query::$max_num_pages) inside WP_UnitTestCase is impractical to do
 * reliably. `template-parts/pagination.php` is the thin call site that
 * resolves those two numbers from the current query/global $wp_query and
 * delegates here.
 */
final class Pagination {
    /**
     * Pure predicate: pagination only makes sense for more than one page —
     * mirrors what `paginate_links()` itself does (returns '' for a single
     * page), exposed standalone so the "should this render at all" rule has
     * one small, trivially-testable unit even though render() below talks
     * to WordPress and can't be pure itself.
     */
    public static function shouldRender(int $totalPages): bool {
        return $totalPages > 1;
    }

    /**
     * @param array<string, mixed> $args Extra args merged into paginate_links(), overriding the defaults below.
     */
    public static function render(int $totalPages, int $currentPage = 1, array $args = []): string {
        if (!self::shouldRender($totalPages)) {
            return '';
        }

        $currentPage = max(1, $currentPage);

        $links = paginate_links(array_merge([
            'total'     => $totalPages,
            'current'   => $currentPage,
            'prev_text' => '<span class="arena-pagination__chevron" aria-hidden="true">&laquo;</span> ' . __('Anterior', 'arena'),
            'next_text' => __('Próxima', 'arena') . ' <span class="arena-pagination__chevron" aria-hidden="true">&raquo;</span>',
            'type'      => 'plain',
        ], $args));

        if (!is_string($links) || $links === '') {
            return '';
        }

        return '<nav class="arena-pagination" aria-label="' . esc_attr__('Navegação de páginas', 'arena') . '">'
            . $links
            . '</nav>';
    }
}
