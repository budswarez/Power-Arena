# Arena — Fatia 2A (Paridade da HOME) — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recomendado) ou superpowers:executing-plans para implementar tarefa a tarefa. Passos usam checkbox (`- [ ]`).

**Goal:** Fazer a home do powerarenaarena (página estática id 5053, montada em WPBakery com 4 blocos `[bs-*]` de listagem) renderizar sob o tema **Arena** com paridade visual, mantendo-a **editável no WPBakery** (abordagem A1: reimplementar os 4 blocos como shortcodes + `vc_map`), além de header/footer/mega-menu.

**Architecture:** WPBakery (plugin `js_composer`) continua provendo `vc_row`/`vc_column`/`vc_single_image`. O Arena reimplementa **apenas** os 4 shortcodes proprietários do Publisher, cada um delegando a um **motor de listagem** comum (`Arena\Listing\Query` puro → args de `WP_Query`; `Arena\Listing\Renderer` → template-part por variante de layout). Tokens de design em `theme.json` + CSS modular. Header/footer via templates padrão + `wp_nav_menu` com Walker de mega-menu.

**Tech Stack:** PHP 8.5 (piso 8.2), WordPress 7.0.2, Vite (CSS/JS já configurados na Fatia 1), CSS moderno, ACF (logo), WPBakery 8.7 (`vc_map`).

## Global Constraints
- `declare(strict_types=1);` em todo PHP; namespace `Arena\`; prefixos `arena_`/`arena-`.
- **Reconstrução limpa:** proibido copiar código do Publisher/Better Framework. Os shortcodes são reimplementados a partir da observação das assinaturas de atributos (já coletadas no recon) e da saída pública renderizada.
- Preservar `wp_head()`/`wp_footer()` e o loop padrão. Compatível com Yoast (não emitir meta/title/schema próprios), LiteSpeed, AMP, ad-inserter, wpDiscuz.
- Ambiente de testes: `MSYS_NO_PATHCONV=1 wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit`. **Quirk conhecido:** run RED imprime mensagem garbled — julgar por contagem/exit, não pelo texto.
- Referência visual = HTML/CSS público servido de `https://www.powerarena.example.com/` (sem credenciais). Cada tarefa de markup deve conferir contra essa referência.
- Blocos-alvo e atributos reais (do recon, page 5053):
  - `bs-modern-grid-listing-7`: hero, `count=5`, `featured_image=1`, últimos posts.
  - `bs-mix-listing-3-1`: 3 col; instâncias Hardware (`category=14236`, `heading_color=#003791`), VALORANT (`17458`, `#129e11`), Free Fire (`11483`, `#e40b20`); `count=4`, `featured_image=0`.
  - `bs-blog-listing-1`: `columns=1`, `show_excerpt=1`, `count=4`, `order_by=rand`, `time_filter=month`.
  - `bs-grid-listing-1`: `columns=4`, `count=8`, título "Últimas notícias", `heading_color=#f42c1a`, fundo `#1b1b1b`, `bs-text-color-scheme=light`, `tabs=cat_filter`.
- Atributos comuns a tratar no motor: `category`, `tag`, `count`, `offset`, `order`, `order_by` (date|rand), `post_ids`, `ignore_sticky_posts`, `time_filter` (month), `disable_duplicate`, `featured_image`, `show_excerpt`, `columns`, `title`, `heading_color`, `title_link`, `bs-text-color-scheme`.
- Menu: reproduzir "Menu TOPO" (33 itens, 3 níveis). Registrar locations com os **mesmos slugs do Publisher** (`main-menu`, `top-menu`, `resp-menu`) para herdar as atribuições existentes, além de manter `arena_primary` como alias. Header classes de referência: `site-header header-style-2 full-width`, `#site-branding`, `nav.main-menu-container`, `ul.main-menu.menu`. Footer: `#site-footer.site-footer`.

---

## Fase 0 — Conteúdo real para testar (pré-requisito, com anonimização)

### Task 1: Importar conteúdo real ANONIMIZADO no wp-env
**Files:** nenhum no repo (operação de ambiente).
**Interfaces:** produz WP local populado com posts/categorias/tags reais para as listagens renderizarem; SEM dados pessoais.

- [ ] **Step 1: Exportar do prod SOMENTE conteúdo público (via plink, read-only)** — usar `wp export` (WXR) filtrando `--post_type=post,page` OU `wp db export` de tabelas de conteúdo apenas. NÃO exportar `wp_users`, `wp_usermeta`, comentários (`wp_comments`/`wp_commentmeta`). Comando de referência (servidor):
  `cd /home/u651042240/websites/M65iPVO8B/public_html && wp export --post_type=post,page --dir=/tmp/arena-exp && ls -la /tmp/arena-exp` — baixar o WXR via `plink ... "cat /tmp/arena-exp/*.xml"` para um arquivo local em `scratchpad/` (fora do repo).
- [ ] **Step 2: Importar no wp-env** com `wp import` (plugin wordpress-importer já disponível) mapeando autores para um único usuário local genérico (`--authors=skip` ou mapear todos para `admin`) — assim nenhum nome/e-mail real de autor entra.
- [ ] **Step 3: `wp search-replace 'https://www.powerarena.example.com' 'http://localhost:8888'`** e verificar que categorias (Hardware=14236 etc.) e posts existem: `wp term list category --fields=term_id,name,count | head`.
- [ ] **Step 4: Criar a página home local** equivalente à 5053 (mesmo `post_content` de shortcodes, capturado no recon) e definir `show_on_front=page` + `page_on_front`. (Sem isso as listagens não têm o palco da home.)
- **Nota de privacidade:** documentar no report que nenhum dado de usuário/comentário foi importado. Se os IDs de categoria diferirem do prod, ajustar os atributos da home local.

---

## Fase 1 — Tokens de design

### Task 2: theme.json + CSS de tokens (fontes e cores reais)
**Files:** Create `theme.json`; Modify `assets/src/css/main.css`; Modify `inc/Assets.php` (preload de fonte); Test `tests/TokensTest.php` (se houver helper puro).
**Interfaces:** CSS custom properties `--arena-font-head` (Oswald), `--arena-font-body` (Barlow), e cores de seção/categoria.

- [ ] Definir em `theme.json` os `settings.typography` (Barlow, Oswald) e `settings.color.palette` com: hardware `#003791`, valorant `#129e11`, freefire `#e40b20`, ultimas `#f42c1a`, dark `#1b1b1b`.
- [ ] Em `main.css`, `@layer base`: variáveis `--arena-font-head:'Oswald',sans-serif; --arena-font-body:'Barlow',sans-serif;` e as cores como custom props; aplicar Barlow no body, Oswald em headings.
- [ ] Carregar as fontes com `display=swap` e `<link rel="preconnect">` + `preload` da fonte crítica em `Assets::enqueue()` (ou self-host futuro). Handles `arena-fonts`.
- [ ] Confere contra o `<link>` real: `fonts.googleapis.com/css?family=Barlow:400,500,600,700|Oswald:500,400&display=swap`.

---

## Fase 2 — Motor de listagem (o coração dos 4 blocos)

### Task 3: `Arena\Listing\Query` — atributos de shortcode → args de WP_Query (PURO, TDD)
**Files:** Create `inc/Listing/Query.php`; Test `tests/Listing/QueryTest.php`.
**Interfaces:** `Arena\Listing\Query::args(array $atts): array` — puro, sem WP; mapeia os atributos comuns para um array de args de `WP_Query`.
- [ ] TDD: teste mapeando `['category'=>'14236','count'=>'4','order'=>'DESC','order_by'=>'date']` → `['cat'=>14236,'posts_per_page'=>4,'order'=>'DESC','orderby'=>'date','ignore_sticky_posts'=>true,...]`; `order_by='rand'`→`orderby=rand`; `time_filter='month'`→`date_query` do mês atual (passar "agora" como parâmetro para manter pureza); `post_ids='1,2'`→`post__in`; `offset`; `tag`→`tag_id`.
- [ ] Implementar minimamente para passar. Manter puro (data via parâmetro injetado, não `time()` interno).

### Task 4: `Arena\Listing\Renderer` + template-parts de card
**Files:** Create `inc/Listing/Renderer.php`; Create `template-parts/listing/{modern-grid,mix,blog,grid}.php`, `template-parts/card/{featured,text,excerpt}.php`; Test `tests/Listing/RendererTest.php` (fumaça: dado layout inválido → fallback; dado WP_Query vazio → markup vazio seguro).
**Interfaces:** `Arena\Listing\Renderer::render(string $layout, \WP_Query $q, array $opts): string` — retorna HTML da listagem (título com `heading_color`, wrapper com classes de esquema claro/escuro, grid de N colunas, cards conforme `featured_image`/`show_excerpt`).
- [ ] Cards reproduzem a estrutura pública do Publisher (conferir classes reais via curl de uma listagem: título, thumb com `width`/`height` explícitos, categoria, data). `fetchpriority`/`decoding` no primeiro card (LCP).
- [ ] Escapar saída (`esc_html`, `esc_url`, `wp_kses_post` no excerpt).

---

## Fase 3 — Os 4 shortcodes `[bs-*]` (A1)

### Task 5: Registrar os 4 shortcodes delegando ao motor (TDD de parsing)
**Files:** Create `inc/Blocks/Shortcodes.php`; Modify `inc/Theme.php` (registrar); Test `tests/Blocks/ShortcodesTest.php`.
**Interfaces:** `Arena\Blocks\Shortcodes::register()` adiciona `add_shortcode('bs-modern-grid-listing-7'|'bs-mix-listing-3-1'|'bs-blog-listing-1'|'bs-grid-listing-1', ...)`; cada callback funde defaults (`shortcode_atts`) + chama `Query::args()` + `Renderer::render(layout,...)`.
- [ ] TDD: para cada tag, `shortcode_atts` preenche defaults corretos e mapeia para o `layout` certo (`modern-grid`|`mix`|`blog`|`grid`) e passa `columns`/`featured_image`/`show_excerpt`/`heading_color`/`bs-text-color-scheme`.
- [ ] Registrar `Shortcodes::register()` em `Theme::boot()`.

### Task 6: `vc_map` dos 4 blocos (edição no WPBakery)
**Files:** Create `inc/Blocks/VcMap.php`; Modify `inc/Theme.php`.
**Interfaces:** `Arena\Blocks\VcMap::register()` no hook `vc_before_init`, com `vc_map()` para os 4 (name, base, category "Arena", params: category, count, columns, featured_image, show_excerpt, heading_color, order_by). No-op se WPBakery ausente (`function_exists('vc_map')`).
- [ ] Sem TDD de render (depende do WPBakery), mas guard testável `VcMap::maps(): array` (definições puras) com teste de shape dos 4.

---

## Fase 4 — Header, footer, mega-menu, front-page

### Task 7: `Arena\Setup` — locations de menu compatíveis
**Files:** Modify `inc/Setup.php`; Test `tests/SetupTest.php` (estender).
- [ ] Registrar locations `main-menu`, `top-menu`, `resp-menu` (slugs do Publisher, para herdar atribuições) + manter `arena_primary` como alias de `main-menu`. Teste: `get_registered_nav_menus()` contém as 4 chaves. (Também cobrir a lacuna do sidebar `arena-primary` pendente da Fatia 1.)

### Task 8: `header.php` + `Arena\Menus\MegaMenuWalker`
**Files:** Create `header.php`, `template-parts/header/{branding,topbar,search}.php`; Create `inc/Menus/MegaMenuWalker.php`; Test `tests/Menus/MegaMenuWalkerTest.php` (fumaça de níveis/classes).
- [ ] `header.php`: `<header id="header" class="site-header header-style-2 full-width">`, `#site-branding` com logo de `Arena\Options::logoId()` (fallback `site-name`/`site-description`), `nav.main-menu-container` com `wp_nav_menu(['theme_location'=>'main-menu','walker'=>new MegaMenuWalker(),'menu_class'=>'main-menu menu bsm-pure clearfix'])`, botão de busca (AJAX opcional depois), sticky.
- [ ] Walker reproduz `menu-item menu-item-has-children` e submenus até 3 níveis; conferir contra markup público capturado.

### Task 9: `footer.php` + `front-page.php` + CSS de layout
**Files:** Create `footer.php`, `front-page.php`; Modify `assets/src/css/main.css` (layout boxed, header-style-2, mega-menu, grids de listagem, seção dark).
- [ ] `footer.php`: `<footer id="site-footer" class="site-footer full-width">` com menu repetido + copyright "© <ano> - Power Arena".
- [ ] `front-page.php`: renderiza `the_content()` da página home (os shortcodes) dentro do wrapper `page-layout-1-col no-sidebar boxed`. Garantir que `do_shortcode` roda (conteúdo padrão do WP já processa).
- [ ] CSS: reproduzir grid de colunas (1/3, 1/4), seção `#1b1b1b` com esquema claro, cores de heading por seção, tipografia Oswald/Barlow. Conferir espaçamentos contra a home pública.

---

## Fase 5 — Validação de paridade e performance

### Task 10: Validação visual + Lighthouse
**Files:** nenhum (verificação).
- [ ] Ativar `arena` no wp-env, abrir a home local: confirmar que os 5 blocos renderizam populados (hero 5, 3-col Hardware/VALORANT/FreeFire, blog 4 c/ excerpt, grid 8 dark), mega-menu com submenus, footer.
- [ ] Comparar lado a lado com a home pública (screenshots) — ajustar CSS até paridade aceitável.
- [ ] Lighthouse mobile na home local (ou via preview em produção): meta ≥ 90–95; registrar métricas LCP/CLS.
- [ ] Rodar a suíte completa: deve seguir verde.

---

## Self-Review (a preencher após escrever tudo)
- Cobertura: tokens (T2), motor (T3-4), 4 blocos (T5-6), header/footer/menu (T7-9), validação (T10), conteúdo de teste (T1). ✓
- Dependências: T3→T4→T5→T6; T7→T8→T9; T1 antes de T4/T10.
- Dados ainda a capturar na implementação: markup/CSS exatos de card e header (via curl da referência pública) — marcado em T4/T8/T9.

## Questões em aberto p/ Fatia 2B (fora deste plano)
- `single.php` (artigo), `category/tag/taxonomy/archive/search/author/404`, sidebar widgets, breadcrumbs Yoast, comentários wpDiscuz.
