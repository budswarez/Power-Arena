<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Generic fallback template (Fatia 2B Task 5) — WordPress' template
 * hierarchy only ever reaches this file when NONE of the more specific
 * templates match the current query: the static front page uses
 * front-page.php, singular posts/pages use single.php/page.php,
 * category/tag/author/date archives use archive.php, search uses
 * search.php, and a missing URL uses 404.php. In practice that means this
 * template should never actually render on this site — but WordPress
 * REQUIRES every theme to ship an index.php, and the Fatia-1 placeholder
 * that lived here (get_header(); a bare `the_title()` loop; get_footer(),
 * no shell/listing/pagination at all) was a near-blank page for any query
 * that DID slip through unmatched (e.g. a custom post type with no
 * archive-{cpt}.php of its own). This makes that fallback behave like a
 * normal blog index instead: the same 2-column shell + the blog-5 listing
 * off the current main `$wp_query` + pagination — the same pattern
 * archive.php's own main listing uses, minus a header/featured block since
 * there is no specific term/author/date to title one after.
 *
 * Must not fight front-page.php: this file is only ever reached when a
 * more specific template (front-page.php included) does NOT match, so as
 * long as the site keeps a static front page configured (Settings →
 * Reading), the home URL keeps resolving to front-page.php and never
 * touches this file at all.
 */

get_header();

get_template_part('template-parts/layout/content-open', null, ['layout' => '2col-right']);

if (function_exists('yoast_breadcrumb')) {
    yoast_breadcrumb('<nav class="arena-breadcrumb">', '</nav>');
}

global $wp_query;
get_template_part('template-parts/listing/archive', null, [
    'query'   => $wp_query,
    'options' => [
        'heading_html' => '',
        'show_excerpt' => true,
        'dark_scheme'  => false,
    ],
]);

get_template_part('template-parts/pagination');

get_template_part('template-parts/layout/content-close', null, ['layout' => '2col-right']);

get_footer();
