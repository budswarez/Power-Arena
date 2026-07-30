<?php
declare(strict_types=1);

namespace Arena;

final class Setup {
    public static function register(): void {
        add_action('after_setup_theme', [self::class, 'loadTextdomain']);
        add_action('after_setup_theme', [self::class, 'themeSupports']);
        add_action('after_setup_theme', [self::class, 'menusAndSizes']);
        add_action('widgets_init', [self::class, 'sidebars']);
        add_filter('body_class', [self::class, 'shellBodyClasses']);
        add_filter('get_custom_logo_image_attributes', [self::class, 'logoIsAboveTheFold']);
    }

    /**
     * GAP C: templates throughout the theme call __()/esc_html_e() with the
     * `arena` text domain (e.g. header.php's skip link, all the pt-BR
     * strings in template-parts/), but nothing ever loaded a .mo/.po file
     * for it — every string silently rendered its raw (already pt-BR)
     * source with no actual translation lookup happening. `languages/`
     * holds the generated `arena.pot` (source template — see README.md for
     * the `wp i18n make-pot` command) that future `.po`/`.mo` translations
     * are built from.
     */
    public static function loadTextdomain(): bool {
        return load_theme_textdomain('arena', get_template_directory() . '/languages');
    }

    public static function themeSupports(): void {
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        // 'comment-list'/'comment-form' (GAP D): opts wp_list_comments()/
        // comment_form() into WP core's semantic HTML5 output
        // (Walker_Comment::html5_comment() — <article>/<footer>, and the
        // built-in threaded reply link/script wiring) instead of the
        // legacy XHTML fallback markup comments.php would otherwise get.
        add_theme_support('html5', [
            'search-form', 'gallery', 'caption', 'style', 'script',
            'comment-list', 'comment-form',
        ]);

        /**
         * task-native-settings: the theme's ONLY configuration UI used to be
         * an ACF options page (`arena_logo` field) — but ACF is not
         * installed on production, so the site owner had literally no way
         * to set a logo. `custom-logo` opts into WordPress' own native
         * mechanism (Aparência → Personalizar → Identidade do site), which
         * needs zero plugins.
         *
         * `height`/`width` (140×640) match the retina (2×) logo asset the
         * theme is shipped with (see DEPLOY.md); the header itself renders
         * the logo at ~64px tall (see `.site-logo`/`.site-branding` in
         * main.css). `flex-height`/`flex-width` are both true because a
         * site owner's own logo will rarely be exactly 640×140 — WordPress
         * only uses the given dimensions as an *aspect-ratio guide* for the
         * media-library cropper when flex is enabled, not a hard
         * requirement, so any reasonable logo image works. `header-text` is
         * an empty array: this theme has no separate "site title displayed
         * next to the logo" text element for `display_header_text()` to
         * toggle — template-parts/header/branding.php's own text fallback
         * (site name + tagline) only ever renders in the ABSENCE of a logo,
         * never alongside one.
         */
        add_theme_support('custom-logo', [
            'height'      => 140,
            'width'       => 640,
            'flex-height' => true,
            'flex-width'  => true,
            'header-text' => [],
        ]);
    }

    /**
     * Locations use the same slugs Publisher (the legacy theme) used —
     * `main-menu`, `top-menu`, `resp-menu` — so existing menu assignments
     * carry over on migration without the site owner reassigning anything.
     *
     * `footer-menu` (task-native-settings, migration gap #6): Publisher also
     * registers this slug (and `off-canvas-menu`, which Arena doesn't need —
     * its own off-canvas panel re-renders the SAME `main-menu`, see
     * header.php, rather than reading a separate location). Registering
     * `footer-menu` here is what lets an owner who had a DIFFERENT menu
     * assigned to Publisher's footer carry it over; footer.php only reads
     * from it when something is actually assigned there (see
     * Setup::footerMenuLocation()), falling back to `main-menu` otherwise —
     * so a fresh Arena install (nothing assigned yet) keeps rendering
     * exactly what it always has.
     */
    public static function menusAndSizes(): void {
        /*
         * Slugs deliberadamente iguais aos do Publisher, para que as
         * atribuições já existentes no site sejam herdadas na troca de tema.
         *
         * NÃO registrar locais que nenhum template renderiza: um local extra
         * chamado "Menu Principal" (`arena_primary`) existia aqui e aparecia
         * no Customizer sem efeito algum — o cabeçalho sempre renderizou
         * `main-menu`. Quem atribuísse o menu ao local de nome mais óbvio não
         * via nada mudar. Cada local abaixo É renderizado por algum template.
         */
        register_nav_menus([
            'main-menu'   => __('Menu Principal (cabeçalho)', 'arena'),
            'top-menu'    => __('Menu Superior (barra escura)', 'arena'),
            'resp-menu'   => __('Menu Mobile (painel lateral)', 'arena'),
            'footer-menu' => __('Menu do Rodapé', 'arena'),
        ]);
        add_image_size('arena-card', 760, 428, true);
    }

    /**
     * Pure resolver (testable): which registered nav-menu location
     * footer.php should render — `footer-menu` when the site owner actually
     * assigned a menu there, `main-menu` otherwise (footer.php's own
     * long-standing behaviour, kept as the fallback so nothing changes for
     * sites that never touch the new location).
     */
    public static function footerMenuLocation(): string {
        $locations = get_nav_menu_locations();
        return !empty($locations['footer-menu']) ? 'footer-menu' : 'main-menu';
    }

    public static function sidebars(): void {
        register_sidebar([
            'id'            => 'arena-primary',
            'name'          => __('Sidebar Principal', 'arena'),
            'before_widget' => '<section id="%1$s" class="widget %2$s">',
            'after_widget'  => '</section>',
            'before_title'  => '<h3 class="widget__title">',
            'after_title'   => '</h3>',
        ]);
    }

    /**
     * Adds the reference's 2-column-shell body classes on single/archive/
     * search/page — matches the reference's measured `page-layout-2-col
     * page-layout-2-col-right active-sticky-sidebar` naming so the shell's
     * CSS hooks (main.css) line up with the reference. `is_archive()`
     * already covers category/tag/date/author/custom-taxonomy archives, so
     * no separate `is_author()` check is needed. `is_page()` covers
     * ordinary WP pages (page.php, GAP 3), which use the SAME shell as
     * posts per the reference.
     *
     * Deliberately excludes the front page even though a static front page
     * IS a Page and so also satisfies `is_page()`: the home stays on
     * front-page.php's own full-width WPBakery layout, never the 2-column
     * shell, so `!is_front_page()` guards that case explicitly rather than
     * relying on is_single()/is_archive()/is_search() never matching it (as
     * used to be the whole guard, back when page.php had no shell classes
     * of its own to worry about).
     *
     * @param array<int, string> $classes
     * @return array<int, string>
     */
    public static function shellBodyClasses(array $classes): array {
        if (is_single() || is_archive() || is_search() || (is_page() && !is_front_page())) {
            $classes[] = 'page-layout-2-col';
            $classes[] = 'page-layout-2-col-right';
            $classes[] = 'active-sticky-sidebar';
        }

        return $classes;
    }

    /**
     * O logotipo é a primeira coisa visível de qualquer página — nunca deve ser
     * lazy-loaded.
     *
     * Medido na home de produção (mobile, 4G lento + CPU 4×): o lazy-loader do
     * EWWW trocava o `src` do logo por um placeholder base64, e o arquivo real
     * só terminava de baixar em **5.668 ms**. Um logo de 3 KB no topo da página.
     *
     * `skip-lazy` é o marcador que faz o EWWW (e os outros lazy-loaders) pularem
     * a imagem — ver `Arena\Media::markAboveTheFold()` para o porquê de
     * `loading="eager"` sozinho não resolver.
     *
     * **Sem `fetchpriority="high"` de propósito:** essa prioridade é para o
     * elemento LCP. O logo é pequeno e não é o LCP; competir com a imagem que É
     * o LCP tornaria o carregamento pior, não melhor.
     *
     * Filtro do core: `get_custom_logo_image_attributes`
     * (`wp-includes/general-template.php`, dentro de `get_custom_logo()`).
     *
     * @param mixed $attr Atributos do `<img>` do logo, vindos do core.
     * @return array<string, string>
     */
    public static function logoIsAboveTheFold(mixed $attr): array {
        // `false`: acima da dobra, mas NÃO é candidato a LCP — ver o segundo
        // parâmetro de markAboveTheFold().
        return Media::markAboveTheFold(is_array($attr) ? $attr : [], false);
    }
}
