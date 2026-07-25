<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Search results (Fatia 2B Task 5). Reuses the same 2-column shell as
 * single.php/archive.php (template-parts/layout/{content-open,content-close}.php,
 * Fatia 2B Task 1) — measured reference shows the search page sharing that
 * shape (breadcrumbs, content column + right sidebar).
 *
 * `<div class="search-header">` carries the ONLY `<h1>` on this page (the
 * term itself, e.g. `Resultados para "…"`) plus the result count from the
 * global `$wp_query->found_posts` — NOT a second query.
 *
 * Main-query decision: exactly like archive.php's own main listing (see
 * that file's docblock for the full rationale), the paginated results
 * listing below is handed the GLOBAL `$wp_query` object directly via
 * template-parts/listing/archive.php — NOT `Arena\Listing\Renderer::render()`,
 * which always builds its OWN brand-new `WP_Query` and would silently
 * reset pagination to page 1 of an unrelated query on every page.
 * `Renderer::render('archive', …)` IS used, but only for the empty-state's
 * "recent posts" fallback below, which intentionally wants its own
 * independent latest-N query — it is a supplementary block, not the
 * paginated result set, and is never itself paginated.
 */

get_header();

get_template_part('template-parts/layout/content-open', null, ['layout' => \Arena\Options::sidebarLayout()]);

if (function_exists('yoast_breadcrumb')) {
    yoast_breadcrumb('<nav class="arena-breadcrumb">', '</nav>');
}

global $wp_query;
$hasResults = $wp_query instanceof WP_Query && $wp_query->have_posts();
$foundPosts = $wp_query instanceof WP_Query ? (int) $wp_query->found_posts : 0;

// get_search_query(false): the raw term, escaped exactly once below —
// get_search_query()'s default arg already esc_attr()'s its return value,
// and escaping that again here would double-encode "&"/quotes.
$searchTerm = get_search_query(false);
?>
<div class="search-header">
    <h1 class="search-header__title">
        <?php
        printf(
            /* translators: %s: the searched term. */
            esc_html__('Resultados para "%s"', 'arena'),
            esc_html($searchTerm)
        );
        ?>
    </h1>
    <p class="search-header__count">
        <?php
        echo esc_html(sprintf(
            /* translators: %d: number of results found. */
            _n('%d resultado encontrado', '%d resultados encontrados', $foundPosts, 'arena'),
            $foundPosts
        ));
        ?>
    </p>
</div>

<?php if ($hasResults): ?>
    <?php
    get_template_part('template-parts/listing/archive', null, [
        'query'   => $wp_query,
        'options' => [
            'heading_html' => '',
            'show_excerpt' => true,
            'dark_scheme'  => false,
        ],
    ]);
    ?>

    <?php get_template_part('template-parts/pagination'); ?>
<?php else: ?>
    <div class="search-empty">
        <p class="search-empty__message">
            <?php esc_html_e('Não encontramos nenhum resultado para essa busca. Tente outros termos ou confira os posts recentes abaixo.', 'arena'); ?>
        </p>

        <?php get_search_form(); ?>

        <div class="search-empty__recent">
            <h2 class="search-empty__recent-heading"><?php esc_html_e('Posts recentes', 'arena'); ?></h2>
            <?php echo \Arena\Listing\Renderer::render('archive', ['count' => '5']); ?>
        </div>
    </div>
<?php endif; ?>

<?php
get_template_part('template-parts/layout/content-close', null, ['layout' => \Arena\Options::sidebarLayout()]);

get_footer();
