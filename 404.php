<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Not-found page (Fatia 2B Task 5). Before this file existed, a 404 fell
 * through to the Fatia-1 placeholder `index.php` (get_header(); a bare
 * title loop that renders nothing for a 404's empty post loop; get_footer())
 * — effectively a near-blank page with the site chrome but no message, no
 * way back in, and no recent content. This replaces that with a proper
 * message, the shared accessible search form (searchform.php), and a
 * supplementary "recent posts" listing, all inside the same 2-column shell
 * as every other content template (template-parts/layout/{content-open,
 * content-close}.php, Fatia 2B Task 1) so the header/sidebar stay intact.
 *
 * HTTP status: WordPress itself sends the 404 status line for any request
 * that resolves to `is_404()` (see WP::send_headers(), called before the
 * template is even chosen) — nothing in this file needs to touch that; it
 * would only need attention if this template called `status_header()`
 * itself, which it deliberately does not.
 *
 * The "recent posts" listing intentionally uses
 * `Arena\Listing\Renderer::render('archive', …)` (its OWN independent
 * latest-N query) rather than the global `$wp_query` — a 404 has no
 * underlying WP_Query worth paginating (`$wp_query->have_posts()` is always
 * false here), so there is no "main listing" to hand off to the shared
 * blog-5 partial the way search.php/archive.php do.
 */

get_header();

get_template_part('template-parts/layout/content-open');
get_template_part('template-parts/layout/content-row-open', null, ['layout' => \Arena\Options::sidebarLayout()]);

/*
 * Breadcrumb do plugin de SEO ativo, resolvido em um só lugar
 * (Arena\Seo::breadcrumb() — Rank Math, Yoast ou SEOPress). Antes cada
 * template chamava `yoast_breadcrumb()` direto, e a trilha desapareceu do site
 * inteiro no dia em que o Yoast foi substituído pelo Rank Math: a função
 * deixou de existir e o `function_exists` simplesmente pulava, sem erro.
 */
\Arena\Seo::breadcrumb();
?>
<div class="not-found-header">
    <h1 class="not-found-header__title"><?php esc_html_e('Página não encontrada', 'arena'); ?></h1>
    <p class="not-found-header__message">
        <?php esc_html_e('O conteúdo que você procura não existe ou foi movido. Use a busca abaixo ou confira os posts recentes.', 'arena'); ?>
    </p>

    <?php get_search_form(); ?>
</div>

<div class="not-found-recent">
    <h2 class="not-found-recent__heading"><?php esc_html_e('Posts recentes', 'arena'); ?></h2>
    <?php echo \Arena\Listing\Renderer::render('archive', ['count' => '6']); ?>
</div>
<?php
get_template_part('template-parts/layout/content-close', null, ['layout' => \Arena\Options::sidebarLayout()]);

get_footer();
