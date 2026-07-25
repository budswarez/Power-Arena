# Tema Arena

Tema WordPress Arena — reconstrução limpa e moderna do tema legado
Publisher, com foco em Core Web Vitals e acessibilidade (Lighthouse a11y
100). Compatível com WPBakery Page Builder (a home usa `[vc_*]` +
shortcodes próprios `[bs-*]`), Yoast SEO (breadcrumbs/schema) e wpDiscuz
(comentários — ver seção própria abaixo).

## Pré-requisitos

- **Docker Desktop** (para o `wp-env`, que sobe WordPress + MySQL + PHP em
  contêineres — nenhuma instalação local de PHP/MySQL é necessária).
- **Node.js 20+** e npm (para o pipeline de assets — Vite).

## Ambiente local (`wp-env`)

O ambiente de desenvolvimento/teste roda inteiramente via
[`@wordpress/env`](https://www.npmjs.com/package/@wordpress/env)
(`wp-env`), configurado por `.wp-env.json` (versão do WordPress/PHP e o
tema em si, montado como o tema ativo) e `.wp-env.override.json` (plugins
extras não versionados no `.wp-env.json` público — ACF, Yoast SEO,
wpDiscuz, WPBakery `js_composer`; overrides locais como tokens de preview,
ver GAP G abaixo). `wp-env` precisa ser instalado globalmente ou invocado
via `npx`:

```bash
npm install -g @wordpress/env   # uma vez, ou use `npx wp-env ...` sempre
```

**A partir desta mesma pasta** (`wp-content/themes/arena/` — é aqui, e não
na raiz da instalação WordPress, que vive o `.wp-env.json`; `wp-env`
resolve a configuração subindo a árvore de diretórios a partir do cwd, e
`"themes": [".", "../arena-child"]` mapeia esta própria pasta e a do tema
filho para dentro de `wp-content/themes/` do ambiente):

```bash
wp-env start   # sobe os containers; primeira vez baixa as imagens (pode demorar)
wp-env stop    # para os containers (mantém os dados)
wp-env destroy # remove os containers E os dados (recomeça do zero)
```

URLs e credenciais padrão do `wp-env`:

| Ambiente | URL                     | Usuário | Senha    |
|----------|-------------------------|---------|----------|
| Site     | http://localhost:8888   | admin   | password |
| Admin    | http://localhost:8888/wp-admin | admin | password |
| Testes   | http://localhost:8889   | admin   | password |

No Windows, comandos que passam caminhos estilo Unix para dentro do
container (ex.: `--env-cwd=wp-content/themes/arena`) precisam do prefixo
`MSYS_NO_PATHCONV=1` no Git Bash, para que o Bash não tente "corrigir" o
caminho para um caminho Windows antes de repassá-lo ao Docker.

## Pipeline de assets (Vite)

CSS/JS do tema vivem em `assets/src/` (`css/main.css`, `js/main.js`) e são
buildados com [Vite](https://vitejs.dev/) para `assets/dist/`
(**gitignored** — nunca commitar `assets/dist/`; é gerado no deploy/CI, não
no repositório).

```bash
npm install     # uma vez, instala vite + lighthouse (devDependencies)
npm run dev     # vite em modo dev (dev server, não usado pelo PHP em produção)
npm run build   # build de produção -> assets/dist/ + assets/dist/.vite/manifest.json
```

`Arena\Assets::enqueue()` (`inc/Assets.php`) lê `assets/dist/.vite/manifest.json`
para resolver o hash do arquivo JS/CSS de produção atual e enfileirar os
`<link>`/`<script>` corretos — **sem esse manifest (build não executado),
`Assets::enqueue()` degrada silenciosamente para não enfileirar nada** (não
gera fatal, mas a página sobe sem o CSS/JS do tema). Rode `npm run build`
sempre antes de verificar visualmente uma mudança.

## Testes (PHPUnit via `wp-env`)

```bash
MSYS_NO_PATHCONV=1 wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit
```

**Quirk conhecido:** quando a suíte tem alguma falha (RED), o `wp-env run`
frequentemente imprime a saída do PHPUnit **truncada/embaralhada** no
terminal (um problema de buffering do `docker exec` sob Windows/Git Bash,
não do PHPUnit em si) — a mensagem de erro real pode aparecer cortada ou
fora de ordem. **Não confie no texto da mensagem para julgar
verde/vermelho**; confie no **exit code** do comando (`$?` — `0` é verde) e
na contagem de `Tests:`/`Assertions:`/`Failures:` que aparece no final,
mesmo que o corpo de uma falha específica tenha saído ilegível. Se
precisar ver o detalhe de uma falha específica, rode só aquele arquivo
(`vendor/bin/phpunit tests/NomeDoTeste.php`) para reduzir o volume de saída
embaralhada.

## Arquitetura

- `functions.php` — define constantes (`ARENA_DIR`, `ARENA_URI`), registra
  um autoloader PSR-4 simples (`Arena\Foo\Bar` → `inc/Foo/Bar.php`) e chama
  `Arena\Theme::boot()`.
- `inc/Theme.php` — ponto único de boot; registra todos os módulos abaixo.
- `inc/Setup.php` — theme supports, menus, sidebars, `body_class` do shell
  2-colunas, `load_theme_textdomain()`.
- `inc/Assets.php` — enfileiramento de CSS/JS via manifest do Vite, Google
  Fonts, correção de `sizes` de imagens lazy-loaded.
- `inc/Layout.php` — mapeia uma chave de layout (`2col-right`, `1col`) para
  as classes CSS das colunas do shell (`template-parts/layout/`).
- `inc/Pagination.php` — paginação (`paginate_links` com `type: 'plain'`).
- `inc/Preview.php` + `mu-plugins/arena-preview.php` — preview do tema em
  produção sem alterar o tema ativo (ver seção própria abaixo).
- `inc/OptionsPanel.php` + `inc/Options.php` — painel de opções ACF (ver
  seção própria abaixo).
- `inc/Compatibility.php`, `inc/Blocks/` — camada de compatibilidade com
  WPBakery (`Blocks\VcMap`, `Blocks\SingleImageHeading`,
  `Blocks\Accordions`) e os shortcodes `[bs-*]` próprios
  (`Blocks\Shortcodes`), que são a "engine de listagem" do tema:
  **`Arena\Listing\Query` → `Arena\Listing\Renderer` → `template-parts/listing/*.php`**.
  `Query::args()` é uma função pura que mapeia atributos de shortcode para
  args de `WP_Query`; `Renderer::render()` executa a query e delega a
  marcação para o layout certo (`grid`, `mix`, `blog`, `modern-grid[-1]`,
  `archive`) em `template-parts/listing/` e aos cards em
  `template-parts/card/` (`hero`, `blog5`, `excerpt`, `text`).
- `inc/Menus/MegaMenuWalker.php` — walker de menu customizado para o mega
  menu + menu off-canvas mobile.
- Templates na raiz (`front-page.php`, `single.php`, `page.php`,
  `archive.php`, `search.php`, `404.php`, `attachment.php`, `comments.php`)
  + `template-parts/` — a marcação propriamente dita, reproduzida
  clean-room a partir da referência medida (não copiada do Publisher).

## Painel de opções (ACF Options Page)

`inc/OptionsPanel.php` registra uma página de opções ACF
(`acf_add_options_page`, slug `arena-options`, capability
`edit_theme_options`) com 4 campos (`inc/OptionsPanel::fields()`, puro e
testável): logo, cor de destaque, fonte base e posição da sidebar.
Requer o plugin **Advanced Custom Fields** ativo (já incluso em
`.wp-env.json`); sem ACF, `OptionsPanel::boot()` retorna sem fazer nada
(`function_exists('acf_add_options_page')` guard) — não fatala. Os valores
são lidos por `inc/Options.php`.

## Tema filho (`arena-child`)

`../arena-child/` é um tema filho mínimo (`Template: arena` em seu
`style.css`) para customizações que não devem viver no tema pai — ele
existe só com `style.css` + `functions.php` hoje, prontos para receber
overrides pontuais sem tocar no Arena. `.wp-env.json` já lista os dois
temas (`"themes": [".", "../arena-child"]`) para que o child fique
disponível no ambiente local.

## Preview em produção

O tema inclui um mu-plugin que permite pré-visualizar o Arena em produção,
requisição a requisição, sem alterar o tema ativo para os demais
visitantes.

**Localização no repositório:** o código-fonte do mu-plugin vive em
`themes/arena/mu-plugins/arena-preview.php` (versionado junto do tema). Um
mu-plugin real do WordPress precisa estar em `wp-content/mu-plugins/`, uma
pasta fora do escopo deste repositório — por isso o arquivo aqui é a fonte, e
o deploy o **copia** para o destino final.

A lógica de decisão pura e testada vive em `Arena\Preview::shouldPreview()`
(`inc/Preview.php`, cobertura em `tests/PreviewTest.php`). O mu-plugin não
pode usar o autoloader do tema (mu-plugins carregam antes dele), então ele
replica a mesma regra inline.

1. Definir em `wp-config.php`: `define('ARENA_PREVIEW_TOKEN', '<token-secreto>');`
2. Deploy: copiar `themes/arena/mu-plugins/arena-preview.php` para `wp-content/mu-plugins/arena-preview.php` na produção.
3. Acessar como admin logado (com a capability `edit_theme_options`), ou usar `?arena_preview=<token-secreto>`.
4. **Cache:** garantir que o preview rode logado (LiteSpeed/WP Rocket ignoram logados) ou
   excluir o parâmetro `arena_preview` do cache. Sem isso, páginas cacheadas mostram o tema errado.

**Testado localmente end-to-end** (não só a lógica pura unitária): montado
via `.wp-env.override.json` (`mappings` + `config.ARENA_PREVIEW_TOKEN`) com
outro tema ativo, confirmando por `curl` as 3 combinações — sem parâmetro
(tema ativo), token certo (Arena), token errado (tema ativo). Ver
`.superpowers/sdd/task-completeness-report.md` para o resultado exato
dessa validação.

## Internacionalização (i18n)

Todas as strings do tema usam `__()`/`esc_html_e()`/etc. com o text domain
`arena`, carregado por `Arena\Setup::loadTextdomain()`
(`load_theme_textdomain('arena', get_template_directory() . '/languages')`,
hookado em `after_setup_theme`). O template de tradução (`.pot`) vive em
`languages/arena.pot` e é gerado com o WP-CLI:

```bash
MSYS_NO_PATHCONV=1 wp-env run cli wp i18n make-pot wp-content/themes/arena wp-content/themes/arena/languages/arena.pot --domain=arena --exclude=node_modules,vendor,assets/dist,.superpowers
```

Para adicionar um idioma, gere um `.po`/`.mo` a partir do `.pot` (ex.:
Poedit, ou `wp i18n make-mo languages/`) e coloque-o em `languages/` — o
tema já está pronto para carregá-lo automaticamente.

## Testes de estilo (TDD)

Onde há lógica (funções puras em `inc/`), o desenvolvimento segue TDD:
teste primeiro (RED), implementação mínima (GREEN), depois refino. Onde só
há marcação/CSS (a maior parte dos `template-parts/`), os testes cobrem a
FORMA renderizada (presença de classes/ids/contagem de headings) via
`load_template()` contra um `go_to()` real, não a lógica pura.

## Não fazer deploy destes diretórios para produção

- `assets/dist/` — gerado pelo build (`npm run build`); o pipeline de
  deploy/CI deve gerar o seu próprio build, não usar um `dist` comitado.
- `node_modules/`, `vendor/` — dependências de desenvolvimento/build.
- **`.superpowers/`** — diretório de trabalho de desenvolvimento (relatórios,
  screenshots de verificação, diffs de revisão, `.pot`/JSON de Lighthouse
  intermediários). É puramente interno ao processo de desenvolvimento —
  não tem nenhuma função em produção e não deve ser enviado ao servidor.
- `tests/`, `phpunit.xml.dist`, `composer.lock`/`composer.json` (a menos
  que o deploy realmente rode `composer install --no-dev` — nesse caso
  `composer.json`/`.lock` sim são necessários, só os `vendor/` resultantes
  é que continuam fora do controle de versão).
