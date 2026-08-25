<?php
declare(strict_types=1);

use Arena\Pagination;

/**
 * `Arena\Pagination::render()` wraps `paginate_links()` in
 * `<nav class="arena-pagination">` — reused by category/tag/author
 * archives and by search results (Task 3 brief). It takes an explicit
 * $totalPages/$currentPage rather than reading the global $wp_query, which
 * keeps it directly unit-testable without needing to simulate a real
 * paginated request (rewrite rules, 'paged' query var, etc. — impractical
 * to fake reliably inside WP_UnitTestCase). `template-parts/pagination.php`
 * is the thin call site that resolves those two numbers from the current
 * query and delegates here; it is covered below too via get_template_part().
 */
class PaginationTest extends WP_UnitTestCase {
    public function test_should_render_is_false_for_zero_or_one_page(): void {
        $this->assertFalse(Pagination::shouldRender(0));
        $this->assertFalse(Pagination::shouldRender(1));
        $this->assertTrue(Pagination::shouldRender(2));
    }

    public function test_render_returns_empty_string_for_a_single_page(): void {
        $this->assertSame('', Pagination::render(1, 1));
        $this->assertSame('', Pagination::render(0, 1));
    }

    public function test_render_outputs_nav_with_links_for_multiple_pages(): void {
        $html = Pagination::render(5, 2);

        $this->assertStringContainsString('<nav class="arena-pagination"', $html);
        $this->assertStringContainsString('aria-label="', $html);
        $this->assertStringContainsString('page-numbers', $html);
        $this->assertStringContainsString('current', $html);
        $this->assertStringContainsString('</nav>', $html);
    }

    public function test_render_clamps_a_current_page_below_one(): void {
        $html = Pagination::render(3, 0);

        $this->assertStringContainsString('<nav class="arena-pagination"', $html);
    }

    public function test_pagination_partial_outputs_nothing_for_a_single_page_query(): void {
        ob_start();
        get_template_part('template-parts/pagination', null, ['total' => 1, 'current' => 1]);
        $html = (string) ob_get_clean();

        $this->assertSame('', $html);
    }

    public function test_pagination_partial_outputs_nav_for_a_multi_page_query(): void {
        ob_start();
        get_template_part('template-parts/pagination', null, ['total' => 3, 'current' => 1]);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('<nav class="arena-pagination"', $html);
        $this->assertStringContainsString('page-numbers', $html);
    }
}
