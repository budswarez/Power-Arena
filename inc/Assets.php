<?php
declare(strict_types=1);

namespace Arena;

final class Assets {
    private const MANIFEST = '/assets/dist/.vite/manifest.json';

    /**
     * Caminho (relativo a ARENA_URI) do único arquivo WOFF2 pré-carregado
     * via `<link rel=preload>` (task-review-fixes-3, FIX 2): Barlow 400,
     * subset latin — o peso realmente usado no corpo do texto acima da
     * dobra em todo template (ver `body{}` em main.css, sem override de
     * font-weight), e o subset que cobre pt-BR (todos os diacríticos do
     * português — ã, ç, é, í, ó, õ, ü — estão dentro de U+0000-00FF). Os
     * outros 11 arquivos em assets/fonts/ (demais pesos + subset latin-ext)
     * NÃO são pré-carregados de propósito: pré-carregar todos adiaria
     * exatamente o que importa para o LCP.
     */
    private const PRELOAD_FONT_PATH = '/assets/fonts/barlow-400-latin.woff2';

    /**
     * Segunda face pré-carregada: Oswald 500, subset latin.
     *
     * Medido na produção (Chrome headless, 1366x768, cache aquecido): acima da
     * dobra a home usa Barlow 400 em 31 elementos e **Oswald 500 em 21** — é a
     * fonte dos títulos de card, do menu e dos headings de bloco. Sem preload,
     * o download dela só começava em ~1.162 ms (depois do CSS agregado ser
     * baixado e parseado), enquanto o Barlow 400 pré-carregado começava em
     * ~889 ms. Com `font-display: swap` isso não bloqueia a pintura, mas troca
     * a fonte no meio da leitura — o "flash" de fonte que o leitor percebe.
     *
     * Continuam FORA do preload, de propósito: os 10 outros arquivos
     * (demais pesos e todos os subsets latin-ext). Cada preload compete pela
     * mesma banda do LCP; o critério aqui é ter medição de uso acima da dobra,
     * não completude. Os `latin-ext` têm `unicode-range` correto e só baixam se
     * algum caractere exigir.
     */
    private const PRELOAD_FONT_PATH_2 = '/assets/fonts/oswald-500-latin.woff2';

    public static function register(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        add_action('wp_head', [self::class, 'printPreloadLink'], 1);
        add_action('wp_head', [self::class, 'printInlineTokens']);
        add_action('after_setup_theme', [self::class, 'registerEditorStyle']);
        add_filter('script_loader_tag', [self::class, 'deferOwnScripts'], 10, 2);
        add_filter('wp_resource_hints', [self::class, 'resourceHints'], 10, 2);
        add_filter('wp_get_attachment_image_attributes', [self::class, 'filterCardImageAttributes'], 10, 3);

        /**
         * Desliga o recurso "auto sizes for lazy-loaded images" do WP 6.7+
         * — mas SÓ nas requisições onde ele de fato quebra, não o site
         * inteiro (whole-branch review, minor finding #12; narrowed from a
         * blanket `__return_false`).
         *
         * A causa raiz: `sizes=auto` é resolvido pelo browser a partir da
         * largura de LAYOUT da imagem no momento do lazy-load, o que quebra
         * dentro de `.hero-tile .img-cont` — o ÚNICO container de imagem
         * absolutamente posicionado (`position:absolute; inset:0`) do tema
         * (ver assets/src/css/main.css; todo outro `.img-cont`/`.img-holder`
         * é `position:relative`, em fluxo normal, onde `auto` resolve
         * corretamente). Mesmo depois do filtro
         * `wp_get_attachment_image_attributes` acima corrigir o `sizes` das
         * thumbs `arena-card`, o WP reprocessa TODO o HTML de `the_content`
         * via `wp_filter_content_tags()` e, como `wp_img_tag_add_auto_sizes()`
         * só verifica se o `sizes` já começa com `auto` (não sabe que já
         * corrigimos o valor), ele PREFIXA `auto,` de novo em qualquer `<img
         * loading=lazy>` — inclusive nas hero tiles.
         *
         * `.hero-tile` só é renderizado em 2 contextos (ver
         * currentRequestRendersHeroTiles()): a home (front-page.php's
         * `[bs-modern-grid-listing-7]`) e a página 1, não paginada, de um
         * arquivo de categoria/tag (archive.php's bloco `modern-grid-1`,
         * guardado por `!is_paged()` no próprio archive.php). Em qualquer
         * OUTRA request — post único, busca, página 2+ de um arquivo,
         * autor, etc. — não existe hero tile na página, então o recurso do
         * WP 6.7 fica LIGADO (comportamento padrão do core, correto para
         * imagens em fluxo normal) em vez de desligado sem necessidade.
         */
        add_filter('wp_img_tag_add_auto_sizes', [self::class, 'autoSizesEnabledForCurrentRequest']);
    }

    /**
     * Callback do filtro `wp_img_tag_add_auto_sizes` — ver o comentário em
     * register() para o raciocínio completo por trás de restringir isto a
     * apenas requisições que renderizam hero tiles.
     */
    public static function autoSizesEnabledForCurrentRequest(bool $enabled): bool {
        return self::currentRequestRendersHeroTiles() ? false : $enabled;
    }

    /**
     * True apenas nos 2 contextos de template que renderizam
     * `card/hero.php` (`.hero-tile .img-cont`): a home, e a página 1 (não
     * paginada) de um arquivo de categoria/tag. Depende de conditional tags
     * do WP (`is_front_page()`/`is_category()`/`is_tag()`/`is_paged()`),
     * então só é confiável DEPOIS que a query principal resolveu — mas o
     * próprio filtro (`wp_img_tag_add_auto_sizes`) só dispara durante a
     * renderização do template, bem depois disso, então nunca é chamado
     * cedo demais.
     */
    private static function currentRequestRendersHeroTiles(): bool {
        if (is_front_page()) {
            return true;
        }

        return !is_paged() && (is_category() || is_tag());
    }

    /**
     * Whole-branch review, minor finding #11: without this, the block
     * editor's canvas had NOTHING backing it — `theme.json` declares
     * Barlow/Oswald as selectable `fontFamilies` (now with real `fontFace`
     * entries too, see theme.json) and this theme's own `color.palette`,
     * but no editor stylesheet meant the front-end look (dark canvas,
     * this theme's actual type scale/spacing) never carried over into the
     * editor preview. `add_editor_style()` accepts a path relative to the
     * theme directory (resolved against BOTH the child theme's and the
     * parent/template theme's directory — see WP core's
     * `get_editor_stylesheets()` — so this keeps working for
     * `arena-child`, which has no `assets/dist` of its own) — same
     * resolved main stylesheet the front end enqueues, hashed filename and
     * all, with the same `style.css` fallback `enqueue()` uses when the
     * manifest is missing.
     */
    public static function registerEditorStyle(): void {
        $manifest = self::manifest();
        $resolved = self::resolveMainAssets($manifest);

        $style = $resolved['styles'][0] ?? null;
        $relativePath = ($style !== null && !$style['fallback'] && is_string($style['file']))
            ? 'assets/dist/' . $style['file']
            : 'style.css';

        add_editor_style($relativePath);
    }

    /**
     * Resolvedor puro (testável): URL do stylesheet externo de fontes a
     * enfileirar. Sempre `null` desde task-review-fixes-3 (FIX 2) — Barlow e
     * Oswald são self-hosted via `@font-face` em main.css (assets/fonts/),
     * não há mais um stylesheet de terceiros para resolver. Mantido (em vez
     * de removido) porque enqueue() ainda consulta este resolvedor
     * defensivamente antes de decidir se enfileira algo.
     */
    public static function fontsUrl(): ?string {
        return null;
    }

    /**
     * Resolvedor puro (testável): não adiciona mais nenhum preconnect de
     * fontes — desde que Barlow/Oswald passaram a ser self-hosted
     * (task-review-fixes-3, FIX 2), nada nesta página fala com
     * fonts.googleapis.com/fonts.gstatic.com, então os hints antigos
     * seriam apenas uma conexão desperdiçada. Repassa qualquer hint que já
     * exista (de outros filtros) sem alteração, para qualquer tipo de
     * relação.
     *
     * @param array<int, string|array<string, string>> $hints
     * @return array<int, string|array<string, string>>
     */
    public static function resourceHints(array $hints, string $relationType): array {
        return $hints;
    }

    /**
     * Resolvedor puro (testável): URL absoluta do único WOFF2 pré-carregado
     * (ver PRELOAD_FONT_PATH acima).
     */
    public static function preloadFontUrl(): string {
        return ARENA_URI . self::PRELOAD_FONT_PATH;
    }

    /**
     * Resolvedor puro (testável): as URLs de TODAS as faces pré-carregadas, na
     * ordem em que são impressas — Barlow 400 primeiro (31 elementos acima da
     * dobra), Oswald 500 depois (21).
     *
     * @return list<string>
     */
    public static function preloadFontUrls(): array {
        return [
            ARENA_URI . self::PRELOAD_FONT_PATH,
            ARENA_URI . self::PRELOAD_FONT_PATH_2,
        ];
    }

    /** Callback `wp_head`: imprime os `<link rel=preload>` das fontes críticas. */
    public static function printPreloadLink(): void {
        foreach (self::preloadFontUrls() as $url) {
            printf(
                '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin="anonymous">' . "\n",
                esc_url($url)
            );
        }
    }

    /**
     * Resolvedor puro (testável): monta o bloco `<style>:root{…}</style>`
     * inline a partir dos tokens resolvidos (task-review-fixes-3, FIX 1) —
     * Arena\Options::cssTokens() já sanitiza cada valor (hex normalizado /
     * pilha de fontes de uma whitelist fixa), então esta função só formata.
     *
     * @param array<string, string> $tokens
     */
    public static function inlineRootStyle(array $tokens): string {
        $decls = '';
        foreach ($tokens as $name => $value) {
            $decls .= $name . ':' . $value . ';';
        }
        return '<style id="arena-inline-tokens">:root{' . $decls . '}</style>' . "\n";
    }

    /** Callback `wp_head`: imprime o override de custom properties a partir das opções ACF salvas. */
    public static function printInlineTokens(): void {
        echo self::inlineRootStyle(Options::cssTokens());
    }

    /** Resolvedor puro (testável): entrada -> dados do manifest. */
    public static function resolve(array $manifest, string $entry): ?array {
        if (!isset($manifest[$entry]['file'])) { return null; }
        return [
            'file' => $manifest[$entry]['file'],
            'css'  => $manifest[$entry]['css'] ?? [],
        ];
    }

    private static function manifest(): array {
        $path = ARENA_DIR . self::MANIFEST;
        if (!is_readable($path)) { return []; }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Resolvedor puro (testável): lista de arquivos CSS -> pares handle/file.
     * O primeiro arquivo recebe o handle 'arena-main' (preservado para
     * compatibilidade com dependências externas); os demais recebem
     * handles únicos indexados: 'arena-main-1', 'arena-main-2', ...
     *
     * @param string[] $cssFiles
     * @return array<int, array{handle: string, file: string}>
     */
    public static function styleHandles(array $cssFiles): array {
        $pairs = [];
        foreach (array_values($cssFiles) as $index => $file) {
            $handle = $index === 0 ? 'arena-main' : 'arena-main-' . $index;
            $pairs[] = ['handle' => $handle, 'file' => $file];
        }
        return $pairs;
    }

    /**
     * Resolvedor puro (testável): decide QUAIS estilos/script enfileirar
     * para a entrada principal, a partir do manifest do Vite.
     *
     * Quando `resolve()` não encontra a entrada JS (manifest ausente —
     * deploy sem `npm run build` — ou stale — rsync/FTP ignorando
     * `assets/dist/.vite/`, um diretório-ponto), este resolvedor NUNCA
     * retorna uma lista de estilos vazia: ele cai para o `style.css` do
     * tema pai sob o handle `arena-main`. Isso é o que garante que
     * `arena-child`'s `wp_enqueue_style(['arena-main'])` sempre encontre
     * uma dependência registrada — o WordPress descarta silenciosamente
     * (sem warning) um enqueue cuja dependência nunca foi registrada, então
     * sem esse fallback o CSS do child some junto com o do pai.
     *
     * Caminho hasheado do manifest é preservado como o caso normal.
     *
     * @param array<string, mixed> $manifest
     * @return array{
     *   js: ?array{handle: string, file: string},
     *   styles: array<int, array{handle: string, file: ?string, fallback: bool}>
     * }
     */
    public static function resolveMainAssets(array $manifest): array {
        $js = self::resolve($manifest, 'assets/src/js/main.js');

        if ($js === null) {
            return [
                'js'     => null,
                'styles' => [['handle' => 'arena-main', 'file' => null, 'fallback' => true]],
            ];
        }

        $styles = array_map(
            static fn (array $pair): array => $pair + ['fallback' => false],
            self::styleHandles($js['css'])
        );

        return [
            'js'     => ['handle' => 'arena-main', 'file' => $js['file']],
            'styles' => $styles,
        ];
    }

    public static function enqueue(): void {
        // task-review-fixes-3, FIX 2: Barlow/Oswald are self-hosted via
        // @font-face in main.css (assets/fonts/) — fontsUrl() resolves to
        // null (nothing to enqueue), kept as a defensive guard rather than
        // deleted, matching the same "resolver can say there's nothing to
        // do" shape as resolveMainAssets()'s own null cases below.
        $fontsUrl = self::fontsUrl();
        if ($fontsUrl !== null) {
            wp_enqueue_style('arena-fonts', $fontsUrl, [], null);
        }

        $manifest = self::manifest();
        $dist = ARENA_URI . '/assets/dist/';
        $resolved = self::resolveMainAssets($manifest);

        foreach ($resolved['styles'] as $style) {
            $src = $style['fallback']
                ? get_template_directory_uri() . '/style.css'
                : $dist . $style['file'];
            wp_enqueue_style($style['handle'], $src, [], null);
        }

        if ($resolved['js'] !== null) {
            wp_enqueue_script('arena-main', $dist . $resolved['js']['file'], [], null, true);
            // The off-canvas submenu toggle's aria-label is built in JS
            // (it's per-submenu, injected at runtime) — these two strings
            // used to be hardcoded pt-BR literals in main.js, bypassing
            // i18n entirely (whole-branch review, minor finding #7).
            // `%s` is a literal placeholder swapped for the submenu's own
            // label client-side (see initOffCanvasMenu() in main.js), not a
            // PHP sprintf() token.
            wp_localize_script('arena-main', 'arenaI18n', [
                'expandSubmenuWithLabel' => __('Expandir %s', 'arena'),
                'expandSubmenu'          => __('Expandir submenu', 'arena'),
            ]);
        }
    }

    /** Aplica defer apenas aos scripts do próprio tema. */
    public static function deferOwnScripts(string $tag, string $handle): string {
        if (str_starts_with($handle, 'arena-')) {
            return str_replace(' src=', ' defer src=', $tag);
        }
        return $tag;
    }

    /**
     * Callback do hook `wp_get_attachment_image_attributes`: apenas
     * repassa para o resolvedor puro abaixo, descartando o `$attachment`
     * (não usado pela lógica de correção do `sizes`).
     *
     * @param array<string, mixed> $attr
     * @param string|int[]         $size
     * @return array<string, mixed>
     */
    public static function filterCardImageAttributes(array $attr, \WP_Post $attachment, $size): array {
        return self::fixCardImageAttributes($attr, $size);
    }

    /**
     * Resolvedor puro (testável): corrige o `sizes` das thumbs `arena-card`.
     *
     * O recurso "auto sizes for lazy-loaded images" do WP 6.7+ prefixa o
     * `sizes` calculado com `auto,` sempre que a imagem tem `loading=lazy`
     * (ver `wp_get_attachment_image()` em wp-includes/media.php, que injeta
     * esse prefixo ANTES de disparar o filtro `wp_get_attachment_image_attributes`).
     * O browser resolve `sizes=auto` a partir da largura de LAYOUT da
     * imagem no momento do lazy-load — dentro do `.hero-tile .img-cont`
     * (absolutamente posicionado, `inset:0`) isso resolve errado e a
     * imagem nunca chega a aparecer (diagnosticado no tile "Epic Games
     * Store libera Foretales": título aparece, imagem fica cinza).
     *
     * Sempre substitui o `sizes` de uma thumb `arena-card` (tenha ele vindo
     * com o prefixo `auto,` do WP ou com o valor genérico padrão calculado
     * a partir do tamanho registrado) pelo valor concreto e correto para o
     * layout de destino (ver self::cardContext()/cardSizes()) — não basta
     * reagir só ao caso `auto`: o padrão do WP para `arena-card` também é
     * genérico demais (sempre `760px`, mesmo quando o tile só ocupa 33vw ou
     * 50vw da viewport num hero de 2-3 colunas), então sempre aplicamos o
     * valor calculado por contexto. Nunca retorna um `sizes` que comece com
     * `auto`. Ignora imagens de qualquer outro tamanho registrado.
     *
     * @param array<string, mixed> $attr
     * @param string|int[]         $size
     * @return array<string, mixed>
     */
    public static function fixCardImageAttributes(array $attr, $size): array {
        if ($size !== 'arena-card') {
            return $attr;
        }

        $class = isset($attr['class']) ? (string) $attr['class'] : '';
        $attr['sizes'] = self::cardSizes(self::cardContext($class));

        return $attr;
    }

    /**
     * Resolvedor puro (testável): infere o contexto de layout de uma thumb
     * `arena-card` a partir das classes do `<img>` — os template-parts
     * marcam o contexto via classe (ver template-parts/card/hero.php).
     */
    public static function cardContext(string $class): string {
        if (str_contains($class, 'hero-tile__img--compact')) {
            return 'hero-compact';
        }
        if (str_contains($class, 'hero-tile__img')) {
            return 'hero-large';
        }
        if (str_contains($class, 'arena-card-context-text')) {
            return 'text';
        }
        if (str_contains($class, 'arena-card-context-list-blog')) {
            return 'list-blog';
        }
        if (str_contains($class, 'arena-card-context-list-archive')) {
            return 'list-archive';
        }
        return 'default';
    }

    /**
     * Resolvedor puro (testável): valor de `sizes` correto por contexto de
     * layout, calculado a partir da largura real do slot no grid (ver
     * assets/src/css/main.css):
     * - `hero-large`: `.mg-row-1 .hero-tile`, 2 tiles por linha (50vw);
     * - `hero-compact`: `.mg-row:not(.mg-row-1) .hero-tile`, 3 por linha (33vw);
     * - `default`: demais cards (mix/blog/grid), slot fixo de 760px
     *   (`add_image_size('arena-card', 760, 428, true)` em inc/Setup.php).
     * Nunca retorna um valor que comece com `auto`.
     */
    public static function cardSizes(string $context = 'default'): string {
        return match ($context) {
            'hero-large'   => '(max-width: 768px) 100vw, 50vw',
            'hero-compact' => '(max-width: 768px) 100vw, 33vw',
            'text'         => '64px',
            'list-blog'    => '(max-width: 600px) calc(100vw - 30px), 210px',
            'list-archive' => '(max-width: 768px) calc(100vw - 30px), 300px',
            default        => '(max-width: 768px) 100vw, 760px',
        };
    }
}
