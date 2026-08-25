<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Thin call site for `Arena\Pagination::render()` — reused by category/tag/
 * author archives AND search results (same markup/CSS both places, per the
 * Task 3 brief). Resolves $total/$current from the current query when the
 * caller doesn't pass them explicitly, which is how a real archive/search
 * template will use it: `get_template_part('template-parts/pagination');`
 * with no $args at all.
 *
 * @var array<string, mixed> $args {
 *     @type int $total   Total number of pages. Defaults to the global
 *                         $wp_query's max_num_pages.
 *     @type int $current Current page number. Defaults to the 'paged' (or
 *                         'page', for a static front page) query var.
 * }
 */

global $wp_query;

$total = isset($args['total'])
    ? (int) $args['total']
    : (int) ($wp_query instanceof WP_Query ? $wp_query->max_num_pages : 0);

$current = isset($args['current'])
    ? (int) $args['current']
    : max(1, (int) (get_query_var('paged') ?: get_query_var('page') ?: 1));

echo \Arena\Pagination::render($total, $current);
