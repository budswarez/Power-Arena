# 02 — Arquitetura

## Princípios que orientam o código

1. **PHP moderno de verdade.** `declare(strict_types=1)` em todo arquivo,
   classes `final`, namespace `Arena\`, tipos em parâmetros e retornos.
2. **Lógica pura separada de efeito.** O que dá para testar sem WordPress fica
   em método estático puro (`Arena\Listing\Query::args()`,
   `Arena\Options::accessibleTextColor()`, `Arena\Settings::sanitize()`).
   O que depende de WordPress fica na borda.
3. **Um dono por responsabilidade.** Cada classe tem um assunto. Quando duas
   precisavam do mesmo dado, o dado virou uma terceira classe (foi assim que
   `Arena\Settings` nasceu).
4. **Comentário explica o "por quê", não o "o quê".** Vários comentários no
   código citam a medição ou o bug que motivou aquela linha. Eles são a memória
   institucional do projeto — não os remova por concisão.

## Carregamento

```
functions.php
  ├── define ARENA_DIR, ARENA_URI, ARENA_VERSION
  ├── autoloader PSR-4-ish (namespace Arena\ → inc/)
  └── Arena\Theme::boot()
        ├── Setup::register()          suportes, menus, tamanhos de imagem, sidebars
        ├── Assets::register()         CSS/JS, fontes, tokens inline, preloads
        ├── OptionsPanel::register()   painel ACF (só se ACF PRO estiver ativo)
        ├── AdminPanel::register()     painel próprio no admin (sem plugin)
        ├── Customizer::register()     mesmas opções, com preview ao vivo
        ├── Compatibility::register()  ajustes de convivência com plugins
        ├── Blocks\Shortcodes          os 4 blocos [bs-*]
        ├── Blocks\VcMap               registro dos blocos no editor do WPBakery
        ├── Blocks\SingleImageHeading  [vc_single_image] com heading
        └── Blocks\Accordions          [accordions]/[accordion] ("Resumo da matéria")
```

O autoloader é próprio (não Composer) porque o tema não pode depender de
`vendor/` em produção — o `vendor/` existe apenas para as ferramentas de
desenvolvimento e **não vai no pacote**.

## Classes (`inc/`)

### Núcleo

| Classe | Responsabilidade |
|---|---|
| `Theme` | orquestrador do boot; a única que conhece a ordem de registro |
| `Setup` | `add_theme_support`, locais de menu, `add_image_size`, sidebars, classes de `body` |
| `Assets` | enfileira CSS/JS a partir do manifest do Vite, imprime tokens CSS e preloads de fonte |
| `Layout` | resolve as classes de coluna do shell (2 colunas, sidebar à esquerda/direita, largura total) |
| `Pagination` | paginação da query principal |
| `Icons` | SVGs inline (sem biblioteca de ícones) |
| `Media` | thumbnails, `sizes`, imagem padrão quando o post não tem destaque |
| `Seo` | ponte para o breadcrumb do plugin de SEO ativo |

### Opções

| Classe | Responsabilidade |
|---|---|
| `Settings` | **fonte única** do esquema de opções: campos, tipos, saneamento, variáveis CSS |
| `AdminPanel` | menu "Arena" no admin, com abas — não depende de plugin nenhum |
| `Customizer` | as mesmas opções no Customizer, com pré-visualização ao vivo |
| `Options` | resolve o valor efetivo (Customizer → ACF → padrão) e monta os tokens CSS |
| `OptionsPanel` | painel do ACF — **só aparece com ACF PRO**, ver [ADR-004](12-decisoes-arquiteturais.md) |

### Listagens (`inc/Listing/`)

| Classe | Responsabilidade |
|---|---|
| `Attrs` | conversão e validação de atributos de shortcode (puro) |
| `Query` | atributos → argumentos de `WP_Query` (puro, sem efeito colateral) |
| `Renderer` | executa a query, escolhe o layout, delega a marcação aos partials |

### Blocos (`inc/Blocks/`)

| Classe | Responsabilidade |
|---|---|
| `Shortcodes` | registra os 4 shortcodes `[bs-*]` |
| `VcMap` | `vc_map()` — faz os blocos aparecerem no editor visual do WPBakery |
| `SingleImageHeading` | `[vc_single_image]` com o heading colorido acima |
| `Accordions` | `[accordions]`/`[accordion]` → `<details>` semântico |

### Outros

| Classe | Responsabilidade |
|---|---|
| `Compatibility` | remove bloat do core; protege o editor de widgets (ver [08](08-compatibilidade-plugins.md)) |
| `Menus\MegaMenuWalker` | walker do menu principal, com submenu em colunas |
| `Preview` | pré-visualização do tema por token, sem ativá-lo (mu-plugin opcional) |

## Templates

### Raiz

```
front-page.php   home (layout do WPBakery, largura total)
index.php        fallback / blog
single.php       matéria
page.php         página
archive.php      categoria, tag, autor, data
search.php       resultados de busca
404.php          não encontrado
attachment.php   anexo
header.php       doctype → cabeçalho → menus → painel mobile
footer.php       rodapé
sidebar.php      área de widgets
comments.php     lista + formulário de comentários
searchform.php   formulário de busca
functions.php    boot
```

### Partials (`template-parts/`)

```
layout/    content-open, content-row-open, content-close   ← o "shell" de 2 colunas
header/    branding, topbar, search
listing/   modern-grid, modern-grid-1, mix, blog, grid, archive   ← um por layout de bloco
card/      hero, featured, text, list, meta                       ← um por formato de card
single/    header, featured, meta, nav, tags, related
archive-header.php, pagination.php
```

A separação `listing/` × `card/` é o que permite quatro blocos diferentes
compartilharem os mesmos cinco formatos de card sem duplicar marcação.

## Fluxo de uma listagem

```
[bs-grid-listing-1 columns="4" count="8" category="123"]
        │
        ▼
Blocks\Shortcodes            recebe os atributos crus do shortcode
        │
        ▼
Listing\Renderer::render()   orquestra
        ├── Listing\Query::args($atts, time())      atributos → args de WP_Query  (PURO)
        ├── new WP_Query($args)                      executa
        ├── buildOptions()                           colunas, esquema de cor, visibilidade
        │      └── consulta Arena\Settings para os PADRÕES globais
        ├── get_template_part('template-parts/listing/grid')
        │      └── para cada post: get_template_part('template-parts/card/…')
        └── wp_reset_postdata()                      sempre
```

Duas regras que o `Renderer` mantém e que já custaram bug quando esquecidas:

- **`wp_reset_postdata()` sempre**, em toda query secundária.
- **A query principal nunca é substituída.** Arquivos e busca passam o
  `$wp_query` global para o partial, para a paginação não se perder.

## Fluxo das opções

```
Arena\Settings::fields()          esquema: id, grupo, label, tipo, default, css_var
        │
        ├──► AdminPanel   monta a tela do admin a partir do esquema
        ├──► Customizer   monta seções/controles a partir do esquema
        └──► Options::cssTokens()
                    │
                    ▼
             Assets::printInlineTokens()   <style id="arena-inline-tokens">:root{…}</style>
                    │
                    ▼
             main.css lê: var(--arena-header-bg, #0b0b0b)
```

**Armazenamento: `theme_mod`.** As duas telas escrevem no mesmo lugar, então não
existe sincronização nem valor divergente. Detalhes e a regra do "valor vazio =
padrão do tema" em [07 — Opções e painel](07-opcoes-e-painel.md).

## Onde as coisas moram

| Preciso mexer em... | Vá para |
|---|---|
| cor, tamanho, espaçamento | `assets/src/css/main.css` (e rode o build) |
| comportamento de menu/busca/painel mobile | `assets/src/js/main.js` |
| um bloco da home | `inc/Listing/` + `template-parts/listing/` + `template-parts/card/` |
| uma opção nova no painel | `inc/Settings.php` (só isso — as telas se montam sozinhas) |
| uma tag no `<head>` | `inc/Assets.php` |
| um local de menu | `inc/Setup.php` |
| convivência com um plugin | `inc/Compatibility.php` |
