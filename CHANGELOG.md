# Changelog

Todas as mudanças relevantes do tema **Arena**.

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/);
versionamento conforme [SemVer](https://semver.org/lang/pt-BR/) — a política e o
que conta como quebra estão em
[docs/16-versionamento.md](docs/16-versionamento.md).

---

## [0.2.12] — 2026-08-14

### Corrigido

- Exibe a legenda da imagem destacada abaixo da imagem quando o post possui
  uma legenda cadastrada.
- Mantém o botão do menu mobile oculto em telas desktop, amplia vídeos
  incorporados para toda a coluna do artigo e centraliza embeds do Twitter/X.
- Destaca hyperlinks dentro do conteúdo com a cor de destaque do tema e estado
  de foco visível.

### Alterado

- Usa a meta description como resumo dos cartões de listagem em categorias,
  buscas e blog, mantendo o excerpt como fallback.

### Notas de atualização

- Nenhuma migração de conteúdo é necessária. Limpe os caches do LiteSpeed,
  Autoptimize e CDN após a atualização.

---

## [0.2.11] — 2026-08-01

### Melhorado

- Posterga o reCAPTCHA do wpDiscuz até a área de comentários se aproximar da
  viewport ou receber interação. A/B em matéria: payload de 1,41 MiB para
  aproximadamente 630 KiB e TBT de ~25 ms para 2–4 ms, sem remover a proteção
  do formulário.

---

## [0.2.10] — 2026-08-01

Otimização focal da home mobile, preservando WPBakery, LiteSpeed e Analytics.

### Melhorado

- Dá prioridade alta aos dois tiles grandes da primeira linha do mosaico. Eles
  têm a mesma área no mobile e o PageSpeed pode eleger qualquer um como LCP.
- Adiciona a variante `arena-card-small` de `240×135` para os cards compactos de
  64px, que antes precisavam escolher uma imagem de pelo menos 300px.
- Emite `sizes` específicos para cards compactos e para listagens blog/arquivo,
  evitando que o navegador trate thumbs pequenas como imagens de largura total.

### Validado

- O carregamento assíncrono do CSS-base do WPBakery foi testado isoladamente em
  home e matéria. Não mudou a mediana do Lighthouse e, portanto, não foi ativado.
- A imagem destacada das matérias já permanece `eager`, fora do lazy-loader e
  com `fetchpriority="high"`; nenhuma regressão foi introduzida nesse template.

---

## [0.2.9] — 2026-08-01

Otimização incremental da home, sem alterar WPBakery, GTM ou a configuração do
LiteSpeed.

### Melhorado

- Adiciona candidatos responsivos de `480×270` aos cards e `640×192` ao banner,
  reduzindo bytes transferidos em telas menores.
- Corrige o `vc_single_image` do WPBakery para preservar `srcset`/`sizes`; o
  componente continua sendo o mesmo e permanece editável pelo painel.
- Carrega CSS/JS do Contact Form 7 somente quando o conteúdo consultado contém
  `[contact-form-7]`.
- Posterga o SDK do OneSignal até a primeira interação ou quatro segundos após
  `window.load`, preservando o código de configuração oficial do plugin.
- Declara `unicodeRange` também nas faces de `theme.json`; antes o WordPress
  imprimia latin e latin-ext como faces globais e o navegador baixava ambos.

### Operação

- Após publicar, gere apenas os novos tamanhos ausentes das imagens usadas na
  home (`wp media regenerate <ids> --only-missing --yes`) e purgue os caches.

---

## [0.2.8] — 2026-07-30

Continuação da 0.2.4: **home mobile de 83 para 92**, matéria 94, categoria 95.
Corrige um defeito que a própria 0.2.4 piorou.

### Corrigido

- **Seis imagens pediam `fetchpriority="high"` na mesma página.** Cada layout de
  listagem marcava o próprio primeiro card como prioritário — e, desde que a 0.2.4
  passou a emitir `skip-lazy`, essas seis também saíam do lazy-load. Prioridade em
  seis imagens não é prioridade: elas disputavam banda exatamente com a imagem que
  é o LCP.

  Duas peças resolvem:

  - **`Arena\Media::claimAboveTheFoldBlock()`** — trinco por requisição: só o
    **primeiro** bloco de listagem que renderiza pode marcar imagens acima da
    dobra. Como os shortcodes executam na ordem do documento, nenhum layout
    precisa saber onde está na página, e vale igual na home (mosaico primeiro) e
    no arquivo (mosaico de destaque primeiro). Os cards genéricos
    (`featured`/`list`/`text`) passaram a ter **padrão `false`** — um primeiro card
    de bloco lá embaixo da página não está acima da dobra.
  - **`Arena\Setup::claimHighPriorityImage()`** — na home, o tema reivindica a vaga
    de prioridade antes de o conteúdo renderizar, via a API do próprio core
    (`wp_high_priority_element_flag()`). Sem isso o core promove a primeira imagem
    acima de 50.000 px², que na home é o banner do WPBakery: 122 KB para exibir em
    352×106, e não é o LCP.

  Resultado: **1** imagem prioritária por página (era 6).

### Resultado medido

| Página | 0.2.4 | 0.2.8 |
|---|---|---|
| home / mobile | 83 · LCP 4,3 s | **92** · LCP **2,9 s** |
| home / desktop | 99 · LCP 0,9 s | 97 · LCP 1,2 s |
| matéria / mobile | 95 · LCP 2,6 s | **94** · LCP 2,6 s |
| categoria / mobile | — | **95** · LCP 2,5 s |

CLS **0,000** em tudo. A home mobile deu 92 nas três execuções — variância zero.

### Trade-off declarado

Tirar a prioridade do banner **ajuda o mobile e atrapalha o desktop** (no desktop
o banner é grande o bastante para ser o próprio LCP). A/B isolado: com o tema
reivindicando, mobile 93 / desktop 97; deixando o core decidir, mobile 89 /
desktop 99.

Escolhemos o mobile: num portal de notícias ele domina o tráfego, é o score mais
fraco e é a versão que o Google usa para indexar.

**O trade-off desaparece se o banner da home for redimensionado** — hoje ele é
servido em 1170×351 para exibir em 352×106. Com imagem adequada, carrega rápido
mesmo em prioridade baixa e o desktop volta a 99 sem custo para o mobile.

### Notas de atualização

- **Nenhuma ação necessária.** Só atributos de `<img>` e um hook novo.
- Se você mantém um layout de listagem próprio em tema filho, ele agora deve
  reivindicar o trinco (`\Arena\Media::claimAboveTheFoldBlock()`) e repassar
  `above_fold` ao card — sem isso o card simplesmente não marca nada, que é o
  padrão seguro.

---

## [0.2.4] — 2026-07-30

Correção de performance com efeito grande e medido: **matéria no mobile foi de 68
para 95 no PageSpeed**, home de 70 para 83.

### Corrigido

- **O lazy-loader adiava justamente a imagem que definia o LCP.** O tema já
  declarava `loading="eager"` nas imagens acima da dobra, mas o **EWWW Image
  Optimizer** (lazysizes) ignorava esse atributo: trocava o `src` por um
  placeholder base64 e movia a URL real para `data-src`. Medido na home mobile
  (throttling 4G lento + CPU 4×): a imagem do LCP terminava em **6.648 ms** e o
  LCP era **6.680 ms**. O logotipo, de 3 KB e no topo da página, terminava em
  5.668 ms pelo mesmo motivo.

  A correção é o tema declarar **`skip-lazy`** junto de `eager` — string que o
  EWWW tem na própria lista de exclusões e que WP Rocket, Perfmatters e o
  lazysizes também reconhecem, então não amarra o tema a um plugin. Centralizada
  em `Arena\Media::markAboveTheFold()`, usada nos 5 pontos que renderizam imagem
  acima da dobra, e no logotipo via o filtro `get_custom_logo_image_attributes`.

  **`fetchpriority="high"` continua em no máximo uma imagem por página.** No
  mosaico da home os tiles visíveis têm **área idêntica** (58.282 px²): todos
  precisam sair do lazy-load, mas três pedidos de prioridade simultâneos anulam o
  próprio conceito. Daí o segundo parâmetro do helper.

  O limite de **3 tiles** acima da dobra é medido (412×823, onde o mosaico
  empilha), não estimado — está como constante comentada em
  `template-parts/listing/modern-grid.php` e travado por teste. Foram três
  iterações até fechar: proteger só 1 tile deixava o LCP em 6,0 s; proteger 2,
  em 5,5 s; os 3, em 4,3 s.

### Resultado medido

Medianas de 3 a 5 execuções do Lighthouse, host do antivírus bloqueado, máquina
sem Docker:

| Página | Antes | Depois |
|---|---|---|
| home / mobile | 70 · LCP 6,5 s | **83** · LCP **4,3 s** |
| home / desktop | 94 · LCP 1,5 s | **99** · LCP **0,9 s** |
| matéria / mobile | 68 · LCP 7,0 s | **95** · LCP **2,6 s** |

CLS **0,000** em tudo. Hoje **LCP = FCP** — a imagem já está na tela quando a
página pinta pela primeira vez.

### Notas de atualização

- **Nenhuma ação necessária.** A mudança é de atributos de `<img>`; nenhum
  template-part removido, nenhuma opção renomeada, nenhum slug alterado.
- Se você usar outro plugin de lazy-load, ele provavelmente já respeita
  `skip-lazy`. Se não respeitar, adicione as classes do tema à lista de exclusões
  dele (`hero-tile__img`, `custom-logo`, `single-featured`).

---

## [0.2.1] — 2026-07-30

Duas correções de defeito relatado pelo dono do site, ambas medidas na página
real antes de virarem código.

### Corrigido

- **As colunas do WPBakery não empilhavam no celular.** Na home, a linha de três
  colunas (Hardware / VALORANT / Free Fire) ficava lado a lado com ~130px cada num
  viewport de 390px — uma palavra por linha — e a página estourava **49px** para a
  direita.

  Causa raiz: a instalação tem a opção do WPBakery
  `wpb_js_not_responsive_css = 1` ("desabilitar elementos responsivos"), herdada
  da época do Publisher. Com ela, o WordPress imprime `vc_non_responsive` na
  classe do `<body>` e o `js_composer.min.css` passa a valer por
  `.vc_non_responsive .vc_row .vc_col-sm-4 { width: 33.33% }` — **sem media query
  nenhum**, ou seja largura fixa de ⅓ em qualquer tela. As regras responsivas
  nativas do js_composer continuam no arquivo, mas nunca chegam a importar. O
  Publisher compensava com CSS mobile próprio; o Arena não tinha nada equivalente.

  Medido depois da correção, em 7 larguras: 360/390/420/767px empilham com
  **zero overflow**; 768/1024/1366px seguem com 3 colunas. Títulos com menos de
  120px de largura: **29 antes, 0 depois**.

  O tema corrige em vez de a opção ser desligada, porque é configuração global do
  construtor e um tema revendável não pode depender de um ajuste que qualquer
  administrador religa sem saber. `vc_col-xs-*` fica de fora de propósito: `xs`
  significa "vale também em telas pequenas", é escolha de quem montou a página.

- **Breadcrumb redesenhado.** Estava com aparência de texto solto e colado na
  borda esquerda, enquanto o `<h1>` logo abaixo começava 15px adentro. Agora:
  recuo alinhado ao conteúdo, separador em chevron (geometria CSS pura, sem texto
  gerado), item atual com peso maior, links sem sublinhado permanente, `:focus-visible`
  visível e hairline inferior.

  O recuo é escopado a `main.content-container > .arena-breadcrumb` porque a
  trilha é renderizada em **dois** lugares: filha direta de `<main>` em
  single/archive, e dentro de `.content-column` (que já dá 15px) em
  page/search/index/404/attachment — uma regra solta produziria 30px e
  desalinharia cinco templates.

  Acessibilidade medida: links 5,33:1, item atual 17,22:1, hover/foco 4,69:1;
  alvo de toque de 16px → **26,8px**; e os dois nós de texto `" - "` que o
  separador antigo deixava na árvore de acessibilidade **desapareceram**.

### Notas de atualização

- **A aparência do breadcrumb muda de propósito** nesta versão — é a correção
  pedida. É a única mudança visual que não depende de configuração.
- Nenhuma outra ação necessária: nada de template-part removido, slug de menu
  alterado ou opção renomeada.

### Ferramenta de build

Mudanças feitas durante o deploy da 0.2.0, que **não alteram o tema entregue** —
o que foi ao ar naquele dia corresponde exatamente à tag `v0.2.0`.

- `bin/package.sh` lia o tema filho da **cópia legada** `themes/arena-child/` em
  vez da canônica `themes/arena/arena-child/`: o primeiro pacote da 0.2.0 saiu
  com o pai em 0.2.0 e o filho em 0.1.0. Agora a fonte é explícita, com aviso se
  cair na legada.
- `bin/package.sh` passou a **abortar** quando a versão do filho difere do pai, ou
  quando `ARENA_VERSION` difere do `style.css`.
- `CHANGELOG.md` passou a ser incluído no pacote, junto de `README.md` e
  `DEPLOY.md`.

---

## [0.2.0] — 2026-07-30

Primeira atualização depois da entrada em produção. O tema deixa de depender de
qualquer plugin para ser configurado, e o breadcrumb deixa de depender de um
plugin de SEO específico.

### Adicionado

- **Painel "Arena" no admin**, com abas (Cores, Tipografia, Layout e largura,
  Blocos e listagens), sem exigir plugin nenhum. Gravação por `admin_post`, nonce
  por aba, capacidade `edit_theme_options`. (`75dcda7`)
- **Seção "Opções do Arena" no Customizer**, gerada do mesmo esquema
  (`Arena\Settings`) — as duas telas gravam no **mesmo `theme_mod`**. (`ca7cf87`,
  `75dcda7`)
- **Logotipo pelo mecanismo do WordPress**: `add_theme_support('custom-logo')` +
  `get_custom_logo()`, definido em *Aparência → Personalizar → Identidade do
  site*. (`ca7cf87`)
- **`Arena\Seo`** — ponte de breadcrumb com ordem Rank Math → Yoast → SEOPress →
  nada, filtro `arena_breadcrumb_html` e
  `Arena\Seo::breadcrumbProvider()` para diagnóstico. (`f68fe45`)
- **`footer-menu`** como local de menu opcional, usado pelo rodapé só quando há
  menu atribuído. (`ca7cf87`)
- **CSS de alinhamento de mídia do core** (`aligncenter`, `alignleft`,
  `alignright`), que o tema não tinha. (`21e87a1`)
- **`preload` do Oswald 500**, escolhido por medição de uso acima da dobra.
  (`2a06ac1`)
- **Dois contornos para a tela `Aparência → Widgets`** (`75dcda7`):
  - interface clássica **quando — e somente quando** a rota REST `/batch/v1`
    estiver ausente (a hospedagem a remove);
  - remoção dos pacotes do editor de posts arrastados por script de plugin
    (cadeia do wpDiscuz, a dois níveis de dependência).
- **Documentação completa do projeto** em `docs/` (16 documentos), incluindo
  diário de bordo com os diagnósticos errados, ADRs, runbook e pendências.

### Corrigido

- **O breadcrumb desaparecia do site inteiro** quando o Yoast foi trocado pelo
  Rank Math: sete templates chamavam `yoast_breadcrumb()` direto, e cada um
  pulava a chamada silenciosamente. (`f68fe45`)
- **O painel de opções nunca aparecia em produção**: usava
  `acf_add_options_page()`, recurso do **ACF PRO**, e o site tem a versão
  gratuita — na verdade, não tem ACF nenhum. Sem tela, não havia como nem
  definir o logotipo. (`ca7cf87`, `75dcda7`)
- **Local de menu "isca"**: `arena_primary` era registrado, chamava-se "Menu
  Principal" e **nenhum template o renderizava** — atribuir o menu do cabeçalho
  ali não fazia nada. Removido; os locais restantes foram renomeados para dizer
  onde aparecem. (`21e87a1`)
- **Alinhamento de imagem não funcionava** mesmo com as regras presentes: o core
  emite `:where(figure){margin:0 0 1em}` **sem camada**, e declaração sem camada
  vence qualquer uma dentro de `@layer`. As regras saíram do `@layer`, com
  teste-guarda. (`eb35577`)
- **O pacote de distribuição era gravado dentro da raiz web**, o que o deixaria
  publicamente baixável. Passou a ir para `renew/entrega-arena/`. (`aaa40f1`)
- **Painel mobile off-canvas**: respeita o atributo `hidden` e corrige o momento
  do foco. (`1e0dab5`)

### Alterado

- **ACF deixou de ser necessário.** Continua suportado como alternativa opcional;
  quando ambos existem, o valor do Customizer/painel vence.
- **Valor vazio significa "padrão do tema"**: a variável CSS não é emitida e cada
  regra usa `var(--token, valor-de-hoje)`. **Atualizar o tema não muda um pixel**
  até alguém escolher algo explicitamente.
- Números fora da faixa declarada são **descartados**, não truncados.

### Notas de atualização

- **Nenhuma ação obrigatória.** A atualização é compatível: nenhum template part
  foi removido, nenhum local de menu em uso mudou de slug, nenhuma opção foi
  renomeada.
- **Se você tinha atribuído um menu ao local `arena_primary`**, reatribua-o a
  **Menu Principal (cabeçalho)** (`main-menu`) — aquele local não fazia nada e foi
  removido.
- Os widgets da sidebar do Publisher continuam exigindo a migração manual única
  descrita em [docs/13-pendencias.md](docs/13-pendencias.md).

---

## [0.1.0] — 2026-07-25

Primeira versão. Enviada ao servidor de produção **inativa**, para validação, e
ativada em 26/07.

### Adicionado

- Núcleo do tema: autoloader PSR-4, boot, pipeline Vite com manifest,
  `Arena\Options`, `Arena\Compatibility`, preview por token (mu-plugin opcional).
- **Os 4 blocos de listagem `[bs-*]`** que a home usa
  (`bs-modern-grid-listing-7`, `bs-mix-listing-3-1`, `bs-blog-listing-1`,
  `bs-grid-listing-1`), reimplementados em limpo sobre um motor único
  (`Arena\Listing\Query` puro + `Renderer`) e mapeados no editor do WPBakery.
- **`[accordions]`/`[accordion]`** ("Resumo da matéria", presente em ~96% das
  matérias) com `<details>`/`<summary>` nativo, zero JavaScript.
- Templates: home, matéria, página, categoria/tag/autor/data, busca, 404, anexo,
  comentários.
- Cabeçalho com mega-menu, barra superior, **painel off-canvas** e barra fixa
  inteligente; rodapé; sidebar com widgets; paginação pelo **loop principal**.
- **Fontes Barlow e Oswald auto-hospedadas** (12 `.woff2`, licença SIL OFL) —
  zero requisições ao Google Fonts.
- Acessibilidade: **100 no Lighthouse** em duas combinações complementares de
  viewport/template; contraste derivado automaticamente da cor de destaque
  escolhida.
- `theme.json`, i18n (`arena.pot`), tema filho `arena-child`, script de
  empacotamento (`bin/package.sh`) e [DEPLOY.md](DEPLOY.md).

### Corrigido antes da entrega

Revisão da branch inteira (53 commits / 88 arquivos) fechou **2 Críticos, 11
Importantes e 13 Menores**. Os dois críticos:

- **manifest do Vite ausente ⇒ zero CSS e JS** — todos os enfileiramentos de
  estilo estavam condicionados ao JS, e `.vite/` começa com ponto (FTP costuma
  pular). Adicionado fallback para `style.css`. (`202abac`)
- **`disable_duplicate` nunca implementado** — blocos que pediam deduplicação
  repetiam posts. (`34851e6`)

Detalhes e o raciocínio de cada correção estão em
[docs/11-diario-de-bordo.md](docs/11-diario-de-bordo.md).

[0.2.8]: https://github.com/budswarez/power-arena/releases/tag/v0.2.8
[0.2.4]: https://github.com/budswarez/power-arena/releases/tag/v0.2.4
[0.2.1]: https://github.com/budswarez/power-arena/releases/tag/v0.2.1
[0.2.0]: https://github.com/budswarez/power-arena/releases/tag/v0.2.0
[0.1.0]: https://github.com/budswarez/power-arena/releases/tag/v0.1.0
