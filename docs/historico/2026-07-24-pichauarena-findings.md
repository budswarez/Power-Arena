# Pichau Arena — Achados do Reconhecimento (parcial, sem SSH)

- **Data:** 2026-07-24
- **Método:** `curl` do HTML público de `https://www.pichauarena.com.br/` (somente leitura, dados públicos, sem PII). SSH por senha **não** foi possível de forma não-interativa (`sshpass` ausente) — metadados via WP-CLI pendentes de acesso por chave ou execução manual pelo usuário.

## Achado crítico: a HOME depende de WPBakery + blocos `[bs-*]` do Publisher

Marcadores encontrados no HTML renderizado da home (contagem):

- **WPBakery:** `vc_row` (5), `vc_row-fluid` (5), `wpb_row` (5), `wpb_column` (7), `wpb_wrapper` (8), `wpb_single_image`, `wpb_animate_when_almost_visible`.
- **Blocos proprietários do Publisher (BetterStudio):** `bs-vc-wrapper` (12), `bs-listing` (6), `bs-listing-single-tab` (6), `bs-listing-listing-mix-3-1`, `bs-pretty-tabs-*`, `bs-pagination*` (9+), `bs-srcset` (25), `bs-light-scheme`, `bs-theme`, `bs-publisher-gamers`.

**Implicação:** para o tema Arena renderizar a home (e provavelmente páginas internas montadas por página) sem o Publisher, é preciso **reimplementar os blocos `[bs-*]` que o site usa** (reconstrução limpa) OU manter a biblioteca de blocos do Publisher ativa. Isso confirma o risco de ordenação registrado na spec e **reordena o roadmap**: a camada de compatibilidade de blocos (antiga "Fatia 2") é pré-requisito para qualquer paridade de home.

## Tokens de tipografia (reais)

- Google Fonts: **Barlow** (400,500,600,700) + **Oswald** (400,500), `display=swap`.
- `<link>`: `fonts.googleapis.com/css?family=Barlow:400,500,600,700|Oswald:500,400&display=swap`.

## Plugins visíveis no HTML

- `js_composer` (WPBakery), `gtranslate`, `gutenberg`. (Lista completa de plugins ativos pendente de WP-CLI.)

## Assets de tema observados

- `wp-content/themes/publisher-child/style.css` apareceu diretamente. O CSS do tema pai provavelmente é combinado/minificado por LiteSpeed/Autoptimize (caminhos não triviais no grep). A confirmar.

## Pendente (requer WP-CLI / acesso por chave OU execução manual do usuário)

1. `wp option get show_on_front` / `page_on_front` — confirmar que a home é uma página WPBakery (fortemente indicado pelo HTML).
2. Contagem exata de conteúdos com `[bs-` (`SELECT COUNT(*) ... post_content LIKE '%[bs-%'`).
3. Lista completa de plugins ativos + versões.
4. Estrutura completa do mega-menu (itens/subitens).
5. Inventário dos tipos de bloco `[bs-*]` de fato usados (define o escopo da camada de compatibilidade).

## Consequência para o planejamento

O "Plano 2" deixa de ser "templates de paridade" isolado. A ordem correta passa a ser:
1. **Inventário dos blocos `[bs-*]` usados** (precisa de WP-CLI/DB ou parsing do conteúdo).
2. **Camada de compatibilidade de blocos** (reimplementar os `[bs-*]` usados) — este é o grosso do trabalho e o ponto que decide se "sair 100% da BetterStudio" é viável sem migração de conteúdo.
3. Só então templates + tokens (Barlow/Oswald, cores) + mega-menu.

---

## RECON COMPLETO (via WP-CLI, plink, somente leitura) — 2026-07-24

Acesso resolvido: `plink` (PuTTY) + senha, não-interativo, somente leitura. Raiz WP:
`/home/u651042240/websites/M65iPVO8B/public_html`.

### Versões (CORREÇÃO — a spec estava certa)
- **WordPress 7.0.2**, **PHP 8.5.8**. O wp-env da Fatia 1 foi montado em 6.4/8.2 → **realinhar** para 7.0.x / PHP 8.5 (ou o mais próximo suportado pelas imagens), e reexecutar a suíte.

### Escopo dos blocos (MUITO menor que o pior caso)
- `show_on_front=page`, `page_on_front=5053`. **Só 1 página** (a home) contém `[bs-`. Posts/arquivos NÃO usam blocos no conteúdo → templates nativos bastam.
- **4 tipos de bloco de listagem** na home:
  - `bs-modern-grid-listing-7` — hero, count=5, featured, últimos posts.
  - `bs-mix-listing-3-1` — 3 colunas: Hardware (cat 14236, heading `#003791`), VALORANT (cat 17458, `#129e11`), FREE FIRE (cat 11483, `#e40b20`), count=4, sem featured (lista de texto).
  - `bs-blog-listing-1` — 1 coluna, com excerpt, count=4, random do mês.
  - `bs-grid-listing-1` — 4 colunas, "Últimas notícias", count=8, fundo escuro `#1b1b1b`, heading `#f42c1a`, tabs=cat_filter.
  - + `vc_single_image` (banner "Evento Pichau Arena 2026" → evento.pichauarena.com.br).
- Todos são variantes de "listagem de posts" parametrizadas por categoria/quantidade/colunas/featured/excerpt/cor — reimplementáveis como 1 template-part parametrizado.

### Tokens de design (reais)
- Fontes: **Barlow** (400/500/600/700) + **Oswald** (400/500).
- Cores de seção/categoria: Hardware `#003791`, VALORANT `#129e11`, Free Fire `#e40b20`, "Últimas" `#f42c1a`, fundo escuro `#1b1b1b`.

### Plugins ativos (CORREÇÕES)
- **SEO = Yoast** (`wordpress-seo` 28.1 + `wordpress-seo-premium` 26.8 + `wpseo-news`) — **NÃO RankMath**. Ajustar `Arena\Compatibility`/notas.
- **Sem WooCommerce** ativo (PII de e-commerce não se aplica; ainda há usuários + comentários wpDiscuz).
- Outros: `js_composer` 8.7.4 (WPBakery), `litespeed-cache` 7.8.1, `ewww-image-optimizer` (não Imagify), `amp`, `ad-inserter`, `google-site-kit`, `trinity-audio`, `onesignal`, `gtranslate`, `classic-editor`+`gutenberg`, `members`, `wpdiscuz`, `contact-form-7`, `hostinger`, `wp-file-manager`.

### Tema ativo
- `publisher-child` 1.0.0 (filho de `publisher`). Arena-child espelha o padrão.

### Mega-menu — "Menu TOPO" (slug `menu-topo`, locations `main-menu,resp-menu`), 33 itens, até 3 níveis
Top: Últimas (/category/noticias/), Hardware (→ Pichau, AMD*, Intel*, NVIDIA* — *=tags), Games (→ Minecraft/Roblox/Resident Evil/GTA = tags), EA FC (/category/fifa/), Free fire, LoL, VALORANT, CS (/category/csgo/), MAIS (/category/esports/ → Influenciadores, Guias, Fortnite, Rainbow Six, PUBG, Dota 2, Mais(→ Mobile Legends, Honor of Kings, Apex, Farlight 84, Wild Rift, Rocket League, Pokémon UNITE, TFT)), Editorial (page).
Segundo menu: "Link Pichau" (slug link-pichau, location top-menu, 1 item).

### Pendências restantes (para a implementação da Fatia 2)
- Extrair os tokens finos (tamanhos de fonte, espaçamentos, cores de header/footer) do CSS servido do Publisher.
- Mapear os templates de single/categoria renderizados (estrutura de card, sidebar widgets).
