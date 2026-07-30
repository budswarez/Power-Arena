# Projeto Arena — Design da Fatia 1 (Núcleo do Tema)

- **Data:** 2026-07-24
- **Autor:** Hudson (hudson@pichau.com.br) + Claude Code
- **Site alvo:** https://www.pichauarena.com.br
- **Status:** Aprovado o design; pendente revisão da spec pelo usuário antes do plano de implementação.

---

## 1. Contexto e realidade do projeto

O objetivo declarado é criar um tema WordPress novo e independente chamado **Arena**, reproduzindo
a aparência e os recursos do tema comercial **Publisher 7.12 (BetterStudio / ThemeForest)** atualmente
instalado, porém com arquitetura moderna e performance (PageSpeed/Core Web Vitals mobile ≥ 95).

Retrato do que está instalado hoje (medido no disco):

- Tema `publisher`: **3.725 arquivos**, **185 MB**, **1.509 PHP / 620 CSS / 102 JS**.
- É uma **plataforma**, não um tema simples: framework proprietário (Better Framework), painel de
  opções, integração com **WPBakery (`js_composer`)** e blocos `[bs-*]`, WooCommerce, bbPress,
  gerenciadores de anúncios, sistemas de comentários.
- Plugins relevantes já ativos: WP Rocket, LiteSpeed Cache, Autoptimize, Imagify, RankMath (SEO
  premium), schema, ad-inserter/better-adsmanager, WooCommerce, Closte.

**Conclusão honesta:** "clonar o Publisher 100%" não é um projeto único — é vários. Reimplementar o
painel, o page builder, os blocos e as combinações de layout é trabalho de equipe por meses. Portanto
o trabalho é **decomposto em fatias**, cada uma com seu próprio ciclo spec → plano → implementação.

**Licença/direito autoral:** o código do Publisher é proprietário (ThemeForest Split License). O Arena
será **reconstrução limpa (clean-room)**: reproduz comportamento/aparência a partir da observação da
saída renderizada. **Não haverá cópia do código-fonte do Better Framework** para dentro do Arena.

---

## 2. Objetivos e motivações (confirmados com o usuário)

O usuário quer, simultaneamente:

1. **Sair da BetterStudio** — ter código próprio, sem dependência de licença/atualizações do Publisher.
2. **Performance sem perder recursos** — 95+ mantendo o que o site usa hoje.
3. **Continuar editando via WPBakery e por um painel de opções.**
4. **Poder revender/reusar o Arena** no futuro.

### Tensões resolvidas

- **Sair da BetterStudio × manter o painel deles:** contraditório — o painel *é* a BetterStudio.
  **Decisão:** substituir por um **painel próprio baseado em ACF**.
- **Editar via WPBakery:** o `js_composer` é um **plugin à parte**, permanece ativo. O que é do tema
  são os blocos `[bs-*]` do Publisher (tratados na Fatia 2).
- **Revender × sob medida:** puxam em direções opostas. **Decisão:** revenda é a **Fatia 4 (futura)**,
  após o núcleo estar provado neste site.

### Decisões técnicas travadas

- **Abordagem A — tema PHP clássico com ferramental moderno** (recusadas: block theme/FSE por conflito
  com WPBakery; headless por descartar plugins/painel).
- **PHP 8** OOP, namespace `Arena\`, autoload PSR-4.
- **Vite** como bundler (manifest com hash).
- **Config via painel próprio ACF** (+ `theme.json` para tokens de design).
- **Ambiente:** desenvolvimento e teste em **local/staging**; validação final em produção via
  **preview por requisição** protegido.
- **CSS:** decisão de Tailwind v4 vs CSS puro modularizado deixada para o plano de implementação
  (ver Questões em aberto).

---

## 3. Decomposição em fatias

| Fatia | Entrega | Ciclo próprio |
|------|---------|----------------|
| **1 — Núcleo** (esta spec) | Tema pai + filho, templates padrão, build Vite, painel ACF mínimo, preview | sim |
| **2 — Compatibilidade de blocos** | Reimplementar os `[bs-*]` que **este site** usa, para páginas WPBakery não quebrarem | sim |
| **3 — Painel completo** | Expandir o painel ACF para as opções do Publisher que o site consome | sim |
| **4 — Produto revendável (futuro)** | Generalizar/configurar para uso em outros sites | sim |

**Risco de ordenação:** se a home/páginas forem montadas com `[bs-*]`, a Fatia 1 mostra conteúdo
quebrado até a Fatia 2 existir. Será verificado por **SSH read-only** na fase de planejamento; se
confirmado, faz-se um MVP da camada de compatibilidade **antes** de concluir a Fatia 1.

---

## 4. Escopo da Fatia 1

**Inclui:**

- Tema pai `arena` + tema filho `arena-child`.
- Templates: `index`, `home`, `single`, `page`, `archive`, `category`, `tag`, `taxonomy`, `author`,
  `search`, `404`, `attachment`.
- Header com **mega-menu** (Hardware→AMD/Intel/NVIDIA, Games, VALORANT, Free Fire, etc.), sidebar,
  footer com repetição do menu.
- Build Vite (manifest + hash + assets minificados).
- Painel de opções **ACF mínimo viável**: logo, cores de destaque, tipografia base, layout de sidebar.
- mu-plugin de **preview em produção** protegido.

**Não inclui (fatias futuras):** blocos `[bs-*]`; painel completo; WooCommerce/bbPress avançado;
generalização para revenda.

---

## 5. Arquitetura de arquivos

```
themes/arena/
├── functions.php            # bootstrap: autoloader PSR-4 + Arena\Theme::boot()
├── style.css                # header do tema (Theme Name: Arena)
├── theme.json               # tokens de design (cores/tipografia/spacing)
├── index.php header.php footer.php single.php page.php archive.php
├── category.php tag.php taxonomy.php author.php search.php 404.php attachment.php
├── inc/
│   ├── Theme.php            # orquestrador — registra os módulos
│   ├── Setup.php            # add_theme_support, menus, sidebars, image sizes
│   ├── Assets.php           # enqueue via manifest do Vite, defer/async dinâmico
│   ├── Optimize.php         # LCP/CLS, preload, remoção de bloat do core
│   ├── Options.php          # ponte tipada com ACF (getters), defaults se ACF ausente
│   ├── Compatibility.php    # RankMath, ads, LiteSpeed/Rocket, WPBakery ativo
│   └── Menus/MegaMenu.php   # Walker do mega-menu
├── template-parts/          # header, footer, post-card, hero, sidebar-widgets
├── assets/src/{js,css,fonts,img}
└── assets/dist/             # gerado pelo Vite (manifest.json + arquivos com hash)

themes/arena-child/
├── style.css                # Template: arena
└── functions.php            # enqueue via get_stylesheet_directory_uri()
```

**Princípios:** `functions.php` mínimo (só bootstrap); um módulo = uma responsabilidade; templates
não chamam `get_field()` diretamente — usam `Arena\Options`.

---

## 6. Assets, build e performance

- **Vite** gera `assets/dist/manifest.json`; `Assets.php` lê o manifest e enfileira com o hash correto.
- **Carregamento condicional** de CSS/JS por tipo de página (`is_single`, `is_home`, `is_archive`…).
- **LCP:** imagem destacada com `fetchpriority="high"` e `decoding="async"`; `width`/`height`
  explícitos em todas as imagens (anti-CLS).
- **Fontes:** `font-display: swap` + `preload` da fonte crítica.
- **CSS crítico** inline no `<head>`; restante adiado.
- **`wp_head()` / `wp_footer()` preservados** nas posições padrão (RankMath, anúncios e cache dependem).
- JS do tema em ES6+ Vanilla/Alpine, sem jQuery no código próprio; `defer`/`async` aplicados só aos
  scripts do tema, sem afetar scripts de plugins.

---

## 7. Painel de opções (ACF)

- `Arena\Options` expõe getters tipados (ex.: `logo(): ?int`, `accentColor(): string`), centralizando
  o acesso; nada de `get_field()` espalhado nos templates.
- ACF é dependência: se o plugin não estiver ativo, o tema **degrada com defaults** (nunca fatal); um
  aviso é exibido no admin.
- Fatia 1 cobre só o essencial; o painel cresce na Fatia 3.
- Campos ACF definidos por PHP (`acf_add_local_field_group`) para versionamento no repositório do tema.

---

## 8. Preview em produção (sem afetar clientes)

- **mu-plugin** `arena-preview.php`. No hook `setup_theme`:
  - Se `is_user_logged_in() && current_user_can('edit_theme_options')` **ou** `?arena_preview=<TOKEN>`
    válido → `add_filter('template', …)` e `add_filter('stylesheet', …)` forçam `arena` **só nessa
    requisição**.
  - Token secreto configurável (constante em `wp-config.php` ou opção).
- **Cache:** preview liberado para usuários logados (já ignoram LiteSpeed/WP Rocket) e/ou regra de
  exclusão do parâmetro `arena_preview` no cache. Documentado no README.
- Demais visitantes continuam vendo o **Publisher**, intocado.

---

## 9. Compatibilidade e tema filho

- **WPBakery permanece ativo** como plugin. Arena preserva o loop padrão (`the_post`, `the_content`,
  `wp_link_pages`, `body_class`, `post_class`).
- `Compatibility.php`: RankMath (não duplicar meta/schema/breadcrumbs), ad-inserter/better-ads (hooks de
  conteúdo preservados), LiteSpeed/WP Rocket.
- **`arena-child`**: `style.css` com `Template: arena`; `functions.php` enfileirando via
  `get_stylesheet_directory_uri()`. Tema pai usa `get_template_directory_uri()`.

---

## 10. Validação — definição de "pronto" da Fatia 1

- **Paridade visual:** comparação lado a lado das páginas-chave (home, single, categoria) — Publisher
  vs Arena, em staging e depois via preview em produção.
- **Performance:** Lighthouse/PageSpeed **mobile ≥ 95** nas páginas-chave.
- **Não-regressão:** RankMath (meta/schema presentes), anúncios renderizando, cache funcionando,
  `wp_head`/`wp_footer` intactos.
- **Child theme:** ativa sem quebrar assets/estilos.

---

## 11. Questões em aberto (para o plano de implementação)

1. **BS blocks na home?** — verificar por SSH read-only se a home/páginas usam `[bs-*]`. Decide a ordem
   das fatias.
2. **Tailwind v4 vs CSS puro modularizado** — decidir no plano, pesando "zero unused CSS" e a
   familiaridade da equipe.
3. **Fonte(s) e tokens exatos** — extrair cores/tipografia reais do site em produção.
4. **Escopo exato do mega-menu** — mapear todas as categorias/subitens do menu atual.

---

## 12. Segurança operacional

- Credenciais SSH/SFTP de produção foram compartilhadas no chat: **rotacionar ao fim do projeto**;
  preferir usuário SFTP restrito à pasta do tema ou chave SSH.
- Nenhuma escrita em produção sem aprovação explícita; acesso na fase de planejamento é **somente
  leitura** para coleta de dados.
- Pasta `docs/` **não deve** ser enviada para produção pelo FileZilla.
- Git será inicializado **escopado à pasta do tema `arena`**, não sobre todo o `wp-content`.
