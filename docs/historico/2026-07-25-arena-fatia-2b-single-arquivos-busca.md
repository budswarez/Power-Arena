# Arena — Fatia 2B (Artigo, Arquivos e Busca) — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Passos usam checkbox (`- [ ]`).

**Goal:** Entregar as páginas que hoje caem no `index.php` mínimo — **artigo (single)**, **categoria/tag/arquivo**, **busca**, **autor** e **404** — com paridade estrutural e visual em relação ao `pichauarena.com.br`, incluindo **sidebar direita**, paginação, breadcrumbs do Yoast e comentários (wpDiscuz).

**Architecture:** Reaproveita o motor já construído: `Arena\Listing\Query` (puro) + `Arena\Listing\Renderer` + template-parts de card. Os arquivos usam o layout de 2 colunas da referência (`content-column` 8/12 + `sidebar-column` 4/12). Novos template-parts para o cabeçalho de arquivo, o card estilo "blog-5" (thumb 300px) e a paginação. `single.php` monta cabeçalho do post, conteúdo, tags, navegação e comentários.

**Tech Stack:** PHP 8.5 (piso 8.2), WP 7.0.2, CSS modular + Vite, ACF, WPBakery ativo, Yoast SEO, wpDiscuz.

## Global Constraints
- `declare(strict_types=1);` em todo PHP; namespace `Arena\`; prefixos `arena_`/`arena-`.
- **Reconstrução limpa:** medir/observar a referência e escrever CSS/PHP próprios. Nunca copiar código do Publisher.
- Preservar `wp_head()`/`wp_footer()`, o loop padrão, `body_class()`, `post_class()`, `wp_link_pages()`.
- **js_composer carrega DEPOIS do CSS do Arena** — regras que disputam com o WPBakery podem exigir `!important`.
- Superfície clara **contínua** na coluna de conteúdo (modelo já adotado na home); preto só nas gutters/faixas escuras.
- Compatibilidade: **Yoast** (não emitir meta/title/schema próprios; usar `yoast_breadcrumb()` se existir), **wpDiscuz** (usar `comments_template()` padrão), ad-inserter (não remover hooks de conteúdo), LiteSpeed.
- Testes: `MSYS_NO_PATHCONV=1 wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit`. **Quirk:** run RED imprime mensagem truncada — julgar por contagem/exit.
- Suíte atual: 85 testes verdes. Não regredir.

## Valores medidos na referência (autoritativos)
**Shell comum a single/arquivo/busca:**
- `body` inclui `page-layout-2-col page-layout-2-col-right boxed active-sticky-sidebar`
- `<main id="content" class="content-container">` → `<div class="container layout-2-col layout-2-col-1 layout-right-sidebar layout-bc-before">`
- Coluna de conteúdo `col-sm-8 content-column` (8/12); sidebar `col-sm-4 sidebar-column sidebar-column-primary` (4/12)
- `layout-bc-before` = breadcrumbs **antes** do conteúdo
- Sidebar é **sticky** (`active-sticky-sidebar`)

**Artigo (single):**
- `<div class="single-featured">` (imagem destacada) → `<div class="post-header-inner">` → `<div class="post-header-title">` → `<h1 class="single-post-title"><span class="post-title">`
- `<div class="post-meta single-post-meta">` com `<span class="post-author-name">` e `<time class="post-published updated">`
- `<div class="entry-content clearfix single-post-content">`
- Também presentes: `post-tags`, navegação `next-prev`, comentários **wpDiscuz** (134 ocorrências no HTML), 1 widget na sidebar
- Template do post na referência: `post-template-10`; permalink de produção: `/{categoria}/{slug}/`

**Categoria/arquivo:**
- `<section class="archive-title category-title with-actions with-desc with-terms">` → `<h1 class="page-heading">`
- Um bloco de destaque no topo: `listing listing-modern-grid listing-modern-grid-1`
- Listagem principal: `listing listing-blog listing-blog-5` → **thumb 300px** (`.listing-item-blog-5 .featured .img-holder{width:300px}`), item `margin-bottom:25px`
- Paginação: `<div class="pagination bs-links-pagination clearfix">`

**Busca:** mesmo shell; `<div class="search-header">` + `listing-blog-5` + paginação + sidebar.

**Espaçamentos/typografia já medidos (reutilizar):** ritmo de bloco 50px; `.section-heading{margin:0 0 15px;font-size:16px;line-height:20px}`; gutters de coluna 15px; blog item `margin-bottom:25px` com borda `rgba(0,0,0,.078)` e `padding-bottom:20px`; largura boxed 1200px; fontes Barlow (corpo) / Oswald (títulos).

---

## Task 1: Shell de layout de 2 colunas + sidebar sticky
**Files:** Create `template-parts/layout/{content-open,content-close}.php`, `sidebar.php`; Modify `inc/Setup.php` (garantir sidebar `arena-primary` registrada — já existe), `assets/src/css/main.css`; Test `tests/SetupTest.php` (estender).
**Interfaces:** partials que abrem/fecham `<main id="content" class="content-container"><div class="container layout-2-col layout-right-sidebar">` + `<div class="content-column">` e renderizam `<div class="sidebar-column">` com `dynamic_sidebar('arena-primary')`.
- [ ] **Step 1: teste** — `tests/LayoutTest.php`: helper puro `Arena\Layout::columnClasses(string $layout): array` retornando `['content'=>'content-column col-8','sidebar'=>'sidebar-column col-4']` para `2col-right` e `['content'=>'content-column col-12','sidebar'=>'']` para `1col`. RED.
- [ ] **Step 2: implementar** `inc/Layout.php` (puro, `final`, `strict_types`) + os partials que o consomem.
- [ ] **Step 3: CSS** — grid de 2 colunas (66.7%/33.3%) com gutter 15px, empilhando em ≤768px; sidebar sticky (`position:sticky; top:<altura da barra fixa + 20px>`), respeitando `prefers-reduced-motion` (sem efeito) e sem overflow horizontal.
- [ ] **Step 4: teste do sidebar** — assert `is_registered_sidebar('arena-primary')` (fecha lacuna antiga) e que `columnClasses` cobre os dois casos. GREEN. Suíte completa.
- [ ] **Step 5: commit** — `feat(layout): 2-column shell with sticky right sidebar`.

## Task 2: `single.php` — página de artigo
**Files:** Create `single.php`, `template-parts/single/{featured,header,meta,tags,nav}.php`; Modify `assets/src/css/main.css`.
**Interfaces:** consome o shell da Task 1.
- [ ] **Step 1:** `single.php` = `get_header()` → shell aberto → breadcrumbs → `while(have_posts())` com `the_post()` → partials (featured, header/h1, meta, `the_content()`, `wp_link_pages()`, tags, nav prev/next) → `comments_template()` → shell fechado → `get_footer()`.
- [ ] **Step 2: breadcrumbs** — `if (function_exists('yoast_breadcrumb')) { yoast_breadcrumb('<nav class="arena-breadcrumb">','</nav>'); }` (sem fallback próprio de schema, para não duplicar o Yoast).
- [ ] **Step 3: meta** — autor (`get_the_author_posts_link()`), data (`<time class="post-published updated">`), contagem de comentários com o ícone SVG já criado na home.
- [ ] **Step 4: featured** — `the_post_thumbnail('full')` com `fetchpriority="high"`, `decoding="async"` e dimensões explícitas (é o LCP da página).
- [ ] **Step 5: comentários** — apenas `comments_template()`; **não** estilizar internals do wpDiscuz (ele traz o próprio CSS). Verificar que a página não fatala com o plugin ausente localmente.
- [ ] **Step 6: CSS** — tipografia do artigo (títulos Oswald, corpo Barlow, largura de leitura confortável), `entry-content` (parágrafos, listas, blockquote, imagens, legendas, tabelas com scroll), tags como chips, nav prev/next.
- [ ] **Step 7: verificar** — abrir um post local; sem erro PHP; H1 único; suíte verde.
- [ ] **Step 8: commit** — `feat(single): article template with Yoast breadcrumbs and comments`.

## Task 3: Card "blog-5" + paginação (reutilizáveis por arquivos e busca)
**Files:** Create `template-parts/card/blog5.php`, `template-parts/pagination.php`; Modify `inc/Listing/Renderer.php` (novo layout `archive`), `assets/src/css/main.css`; Test `tests/Listing/RendererTest.php` (estender).
- [ ] **Step 1: teste** — `Renderer::render('archive', [...])` retorna HTML contendo a classe do card `listing-item-blog-5` e os títulos dos posts criados. RED.
- [ ] **Step 2: implementar** o layout `archive` + `blog5.php`: thumb **300px** à esquerda (dimensões explícitas), título, meta (autor • data • comentários), excerpt; item `margin-bottom:25px` com borda `rgba(0,0,0,.078)` e `padding-bottom:20px`; último item sem borda.
- [ ] **Step 3: paginação** — `paginate_links()` com wrapper `<div class="arena-pagination">`; estilo com o acento `#f42c1a` no item ativo/hover.
- [ ] **Step 4:** GREEN + suíte completa. **Step 5: commit** — `feat(listing): archive card + pagination`.

## Task 4: `archive.php` + `category.php` + `tag.php` + `author.php`
**Files:** Create `archive.php`, `category.php`, `tag.php`, `author.php`, `template-parts/archive-header.php`.
- [ ] **Step 1:** cabeçalho de arquivo — `<section class="archive-title">` com `<h1 class="page-heading">` (`single_term_title()`/`get_the_archive_title()`), descrição do termo (`term_description()`) quando houver, e lista de subtermos quando existirem (a referência mostra `with-terms`).
- [ ] **Step 2:** loop do arquivo renderizando via `Renderer::render('archive', …)` (ou o loop padrão com o card `blog5`), seguido da paginação; sidebar via shell.
- [ ] **Step 3:** `category.php`/`tag.php`/`author.php` reaproveitam `archive.php` (sem duplicação — hierarquia do WP resolve; criar apenas se precisarem de diferença real; caso contrário deixar só `archive.php` e documentar).
- [ ] **Step 4:** verificar categoria local (ex.: Hardware) renderizando com sidebar e paginação; suíte verde. **Step 5: commit** — `feat(archive): term archives with header, listing and pagination`.

## Task 5: `search.php` + `404.php` + `searchform.php`
**Files:** Create `search.php`, `404.php`, `searchform.php`.
- [ ] **Step 1:** `searchform.php` — formulário acessível (`role="search"`, label associado) usado pelo ícone de busca do header e pelas páginas.
- [ ] **Step 2:** `search.php` — `<div class="search-header">` com o termo e a contagem (`Resultados para "X"`), listagem `archive` + paginação + sidebar; estado vazio com sugestão e o formulário.
- [ ] **Step 3:** `404.php` — mensagem, formulário de busca e uma listagem de posts recentes (reutilizando o motor).
- [ ] **Step 4:** verificar `/?s=valorant` e uma URL inexistente; suíte verde. **Step 5: commit** — `feat(search): results, empty state and 404`.

## Task 6: Validação de paridade e não-regressão
- [ ] **Step 1:** capturar a referência (recipe que funciona: `--host-resolver-rules="MAP * 127.0.0.1, EXCLUDE www.pichauarena.com.br, EXCLUDE pichauarena.com.br"`) para **single**, **categoria** e **busca**; capturar as nossas equivalentes; comparar lado a lado e registrar diferenças honestamente.
- [ ] **Step 2:** conferir em 360/420/768/1400px que não há overflow horizontal.
- [ ] **Step 3:** confirmar que Yoast continua emitindo meta/schema (grep no HTML por `yoast`/`og:`), que os hooks de anúncio seguem intactos e que a home **não** regrediu (contagens: `post-title`≈29, `[vc_`=0, `site-logo`=1, `sizes="auto`=0).
- [ ] **Step 4:** suíte completa verde; atualizar o ledger.

---

## Self-Review
- Cobertura: shell/sidebar (T1), single (T2), card+paginação (T3), arquivos (T4), busca/404 (T5), validação (T6). ✓
- Dependências: T1 → T2/T3; T3 → T4/T5; T6 por último.
- Reaproveitamento: o motor de listagem e os tokens/CSS da home são reutilizados — sem duplicar lógica de query.
- Fora de escopo (registrado): comentários customizados (wpDiscuz cuida), AMP, WooCommerce, bbPress, widgets customizados do Publisher, seletor de idioma.
