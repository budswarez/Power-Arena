<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Term archives (category/tag), author archives and generic date archives
 * (Fatia 2B Task 4) — ONE template for all of them. WordPress' own template
 * hierarchy already falls through to `archive.php` for every one of these
 * query types once no more specific `category-*.php`/`tag-*.php`/
 * `author-*.php`/`date.php` exists, so none of those were created: the
 * only real differences between them (title text, description source,
 * whether child-term chips or a "featured" block make sense) are small
 * enough to live as conditionals inside template-parts/archive-header.php
 * and this file, rather than 4 near-identical template copies. See the
 * Task 2B/4 report for the explicit rationale.
 *
 * Reuses:
 *  - the 2-column shell (template-parts/layout/{content-open,content-row-
 *    open,content-close}.php, Fatia 2B Task 1), same as single.php.
 *  - template-parts/archive-header.php for the <h1>/description/child-terms.
 *  - template-parts/listing/archive.php (the blog-5 card layout, Task 3) for
 *    the MAIN listing.
 *  - template-parts/pagination.php (Task 3) for the pager.
 *
 * Main-query decision: the paginated listing below is rendered by handing
 * template-parts/listing/archive.php the GLOBAL $wp_query object directly,
 * NOT via Arena\Listing\Renderer::render('archive', …) — Renderer::render()
 * always builds a brand-new WP_Query from the given $atts
 * (`new \WP_Query(Query::args($atts, time()))`), which has no idea about
 * the current page's `paged` query var or the archive's own query vars
 * (cat/tag/author/date); using it here would silently reset pagination to
 * page 1 of an unrelated "latest N posts" query every time. Because
 * get_template_part() only isolates variable SCOPE (not object identity),
 * passing the live $wp_query object lets the partial call
 * `$query->have_posts()`/`$query->the_post()` — the exact same method
 * calls the global have_posts()/the_post() functions delegate to — so this
 * is byte-for-byte the same iteration as a normal `while (have_posts())`
 * loop, just performed inside the shared partial instead of inline here.
 * Arena\Listing\Renderer::render() IS still used, but only for the small
 * "featured" modern-grid block above the listing (see below), which
 * intentionally wants its OWN independent top-5 query scoped to the
 * current term rather than the paginated one.
 *
 * Featured block: only rendered for category/tag term archives (the
 * queried object is a WP_Term whose taxonomy the shortcode-oriented
 * Arena\Listing\Query already knows how to filter by — `cat`/`tag_id`),
 * and only on page 1 (`!is_paged()`). Author and date archives have no
 * such "term" to scope a curated top-5 pick to, and Arena\Listing\Query
 * has no author/date filter of its own to add just for this, so they get
 * the plain listing only — noted here rather than silently showing an
 * unscoped "latest 5 sitewide" block that would have nothing to do with
 * the archive being viewed. Restricting it to page 1 avoids repeating the
 * exact same curated top-5 pick, unchanged, at the top of every later
 * page — it is a "featured" callout, not part of the paginated set.
 *
 * Featured/listing overlap: deliberately NOT de-duplicated. Excluding the
 * featured block's own post IDs from the main listing on page 1 would
 * require knowing those IDs BEFORE the main query runs (a `pre_get_posts`
 * hook on the main query, fed by a separate lookahead query for the same
 * top-5) — extra global-query-mutating machinery whose main payoff is
 * cosmetic (avoiding an already-recent post appearing twice near the top
 * of the same page). Skipped as "not cheap to do cleanly" per the brief's
 * own escape hatch.
 *
 * Order (Task 2B polish pass): the reference renders the featured block
 * FIRST, then the archive header (label chip + H1 + description + child
 * terms), then the paginated listing — NOT header-then-featured as this
 * template originally had it. tests/ArchiveTemplateTest.php's
 * test_featured_block_renders_before_archive_header() locks this order via
 * strpos() position comparison.
 *
 * Breadcrumb + featured mosaic BOTH render ABOVE the 2-column row entirely
 * (task-uifix BUG 6) — a full-width band directly inside `<main>`, spanning
 * the whole boxed 1200px column, not squeezed into the 8/12 content column
 * beside an empty sidebar column. The mosaic's own 56/44 tile proportions
 * (template-parts/listing/modern-grid-1.php) are percentage/aspect-ratio
 * based, so it simply renders wider here — no markup change needed there.
 * Only the archive header + paginated listing stay inside the row (the
 * sidebar's widgets sit beside THOSE, not beside the mosaic). Rendered
 * between template-parts/layout/content-open.php (opens just `<main>`) and
 * template-parts/layout/content-row-open.php (opens the
 * `.container`/`.content-column` row) — see both partials' own docblocks.
 */

get_header();

get_template_part('template-parts/layout/content-open');

/*
 * Breadcrumb do plugin de SEO ativo, resolvido em um só lugar
 * (Arena\Seo::breadcrumb() — Rank Math, Yoast ou SEOPress). Antes cada
 * template chamava `yoast_breadcrumb()` direto, e a trilha desapareceu do site
 * inteiro no dia em que o Yoast foi substituído pelo Rank Math: a função
 * deixou de existir e o `function_exists` simplesmente pulava, sem erro.
 */
\Arena\Seo::breadcrumb();

$queriedObject = get_queried_object();
if (!is_paged() && $queriedObject instanceof WP_Term && in_array($queriedObject->taxonomy, ['category', 'post_tag'], true)) {
    // `modern-grid-1`, NOT the home's `modern-grid`: the reference renders
    // this featured block with an IRREGULAR mosaic (1 large + 1 medium +
    // 2 small tiles = 4 posts), not the home's uniform 2-large + 3-compact
    // pattern — see template-parts/listing/modern-grid-1.php's docblock.
    $featuredAtts = ['count' => '4'];
    if ($queriedObject->taxonomy === 'category') {
        $featuredAtts['category'] = (string) $queriedObject->term_id;
    } else {
        $featuredAtts['tag'] = (string) $queriedObject->term_id;
    }
    echo \Arena\Listing\Renderer::render('modern-grid-1', $featuredAtts);
}

get_template_part('template-parts/layout/content-row-open', null, ['layout' => \Arena\Options::sidebarLayout()]);

get_template_part('template-parts/archive-header');

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

get_template_part('template-parts/layout/content-close', null, ['layout' => \Arena\Options::sidebarLayout()]);

get_footer();
