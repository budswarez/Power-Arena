# Changelog

Todas as mudanças relevantes do tema **Arena**.

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/);
versionamento conforme [SemVer](https://semver.org/lang/pt-BR/) — a política e o
que conta como quebra estão em
[docs/16-versionamento.md](docs/16-versionamento.md).

---

## Não lançado

Mudanças de ferramenta de build, feitas durante o deploy da 0.2.0. **Não alteram
o tema entregue** — o que está em produção corresponde exatamente à tag `v0.2.0`.

### Corrigido

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

[0.2.0]: https://github.com/budswarez/arena-wp/releases/tag/v0.2.0
[0.1.0]: https://github.com/budswarez/arena-wp/releases/tag/v0.1.0
