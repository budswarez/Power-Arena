# 12 — Decisões arquiteturais

Cada decisão que moldou o tema, com o **contexto** em que foi tomada, as
**consequências** que ela impõe e as **alternativas descartadas**. O formato é o
de ADR (*Architecture Decision Record*).

Se você pretende mudar algo que contradiz um destes registros, leia o registro
primeiro: ele provavelmente já explica por que a alternativa óbvia não foi
escolhida.

---

## ADR-001 — Reimplementação limpa (clean-room)

**Contexto.** O site rodava o Publisher (BetterStudio), tema comercial. O
objetivo era substituí-lo sem perder aparência nem funcionalidade — e o tema
resultante deveria poder ser **revendido**.

**Decisão.** **Nenhum arquivo, nenhuma linha do Publisher foi lida para produzir
código do Arena.** A compatibilidade foi obtida assim:

1. medir a **saída renderizada** (HTML) e os **valores computados de CSS** no
   site público;
2. escrever código próprio que produza o mesmo resultado observável.

**Consequências.**

- Restrição de licença satisfeita, revenda viável.
- As implementações são genuinamente diferentes por dentro:
  `<details>`/`<summary>` em vez do *collapse* do Bootstrap; flex/grid em vez de
  `float` + jQuery; `paginate_links()` em vez de um paginador próprio; `<img>`
  real com `srcset` em vez do mecanismo proprietário de *lazy background*.
- **Onde a referência tem um comportamento estranho, a estranheza é reproduzida
  de propósito** — como o hero repetir posts do grid (ver
  [Erro 5](11-diario-de-bordo.md#erro-5--corrigir-para-o-lado-errado-quase)).
  Paridade quer dizer paridade.
- Custo: cada valor visual precisou ser **medido**, não copiado. Foi a parte mais
  lenta do projeto.

**Alternativas descartadas.** Tema filho do Publisher (não resolve a
dependência), *fork* do Publisher (licença), redesenhar a interface (o dono do
site quer o site que já tem).

---

## ADR-002 — Manter o WPBakery em vez de migrar o conteúdo

**Contexto.** A home (página estática) é montada com WPBakery e usa **4** tipos de
bloco `[bs-*]` do Publisher. Todo o resto do site — posts, categorias, tags,
busca — usa conteúdo padrão.

**Decisão.** Manter o WPBakery e **reimplementar os 4 blocos**, registrando-os
também no editor visual.

**Consequências.**

- A redação continua editando a home exatamente como antes, **sem
  retreinamento** — que era um requisito explícito.
- O WPBakery passa a ser **dependência obrigatória** do tema.
- O tema herda a briga de cascata com o `js_composer` (ver
  [ADR-007](#adr-007--css-em-layer--e-o-preço-disso)).
- O conteúdo dos posts **não** precisou ser tocado — inclusive os
  `[accordions]` do Publisher presentes em ~96% das matérias, que também foram
  reimplementados.

**Alternativas descartadas.** Reescrever a home como `front-page.php` nativo
(mais leve, mas tiraria a home das mãos da redação e exigiria migrar conteúdo);
migrar tudo para blocos do Gutenberg (projeto muito maior, e não pedido).

---

## ADR-003 — Um motor de listagem, não quatro

**Contexto.** Os 4 blocos `[bs-*]` são variações de listagem de posts,
parametrizadas por categoria, quantidade e colunas.

**Decisão.** Uma função **pura** (`Arena\Listing\Query`) que traduz atributos de
shortcode em argumentos de `WP_Query`, e um `Renderer` que recebe *layout* +
atributos e escolhe os *template-parts*. Os 4 shortcodes são casos do mesmo
motor.

**Consequências.**

- Fonte única da verdade: corrigir a interpretação de um atributo corrige os 4
  blocos.
- A parte difícil (tradução de atributos) é **pura**, logo testável sem
  WordPress: limites, `count="-1"`, categorias separadas por vírgula, fuso
  horário, cor injetada em atributo.
- O `Renderer` monta a **própria** query — o que tem uma consequência importante,
  ver [ADR-005](#adr-005--arquivos-usam-o-loop-principal-o-renderer-só-blocos).

---

## ADR-004 — Painel de opções próprio, sem depender de plugin

**Contexto.** A primeira versão do painel usava `acf_add_options_page()` —
recurso do **ACF PRO**. A produção tem a versão gratuita, e depois se descobriu
que **não tem ACF nenhum**. Resultado: nenhuma tela de configuração existia, e o
dono do site não conseguia nem definir o logotipo.

**Decisão.** Três peças sobre uma **fonte única da verdade** (`Arena\Settings`,
o esquema dos campos):

| Peça | Papel |
|---|---|
| `Arena\AdminPanel` | menu "Arena" no admin, com abas. Zero plugins |
| `Arena\Customizer` | as mesmas opções, com pré-visualização ao vivo |
| `Arena\Options::cssTokens()` | emite as variáveis CSS resolvidas no `wp_head` |

As duas telas gravam no **mesmo `theme_mod`**. Resolução:
`theme_mod → ACF (se ativo) → padrão`.

**Consequências.**

- Não existe estado duplicado, então não existe sincronização para dar errado.
- O ACF continua **suportado como opcional**, nunca necessário.
- O logotipo usa o mecanismo do próprio WordPress
  (`add_theme_support('custom-logo')`), então está onde qualquer pessoa que
  conhece WordPress procura: *Aparência → Personalizar → Identidade do site*.

**Alternativas descartadas.** Exigir ACF PRO (custo para o dono do site, e foi
justamente o erro original); só Customizer (pior para editar muitos campos de
uma vez); só painel próprio (perde a pré-visualização ao vivo).

Ver também [07 — Opções e painel](07-opcoes-e-painel.md) e
[Erro 4](11-diario-de-bordo.md#erro-4--painel-de-opções-que-nunca-apareceu).

---

## ADR-005 — Arquivos usam o loop principal; o `Renderer`, só blocos

**Contexto.** O `Renderer` monta a própria `WP_Query`. Se a listagem principal de
uma categoria fosse renderizada por ele, **a paginação quebraria**: o WordPress
pagina o `$wp_query` global, não uma query secundária.

**Decisão.** Nos arquivos (`archive.php`, `search.php`), a listagem principal
percorre o **loop principal**. O `Renderer` é usado apenas para o bloco de
destaque da página 1 e para os blocos `[bs-*]` da home.

**Consequências.**

- Paginação correta — e **travada por teste**: página 1 e página 2 não têm posts
  em comum.
- Plugins que se penduram no loop principal continuam funcionando.
- Disciplina obrigatória de `wp_reset_postdata()` em toda query secundária.

---

## ADR-006 — Fontes auto-hospedadas, com preload medido

**Contexto.** Barlow e Oswald vinham do Google Fonts: terceiro no caminho do LCP,
e IP de visitante enviado para fora (LGPD).

**Decisão.** Servir as 12 faces `.woff2` do próprio tema (licença SIL OFL
incluída) e **pré-carregar apenas as faces medidas acima da dobra** — Barlow 400
e Oswald 500.

**Consequências.**

- Zero requisições a `googleapis`/`gstatic`; CLS melhorou (0,0214 → 0,0054 na
  matéria).
- O critério do `preload` é **uso medido**, não completude: os outros 10 arquivos
  ficam de fora porque cada `preload` disputa banda com o LCP.
- Testes cobrem também **a existência dos arquivos em disco** — um `preload` que
  dá 404 é pior que nenhum.
- Números e método em [10 — Medições](10-medicoes-e-performance.md).

---

## ADR-007 — CSS em `@layer` — e o preço disso

**Contexto.** O tema organiza o CSS em camadas (`@layer`) para ter uma ordem de
cascata previsível dentro da própria folha.

**Decisão.** Manter as camadas, **documentando a consequência** em vez de
escondê-la.

**Consequências — leia antes de mexer no CSS.**

> Uma declaração **sem camada** vence qualquer declaração **dentro de `@layer`**
> na mesma origem, **independentemente da especificidade**.

Isso significa que:

- o CSS de plugin (o `js_composer` não usa camadas) **passa por cima** do tema.
  Daí os **3 `!important`** que sobrevivem, todos contra `.vc_col-has-fill`,
  todos comentados no `main.css`;
- regras que precisam vencer o **core** (que emite `:where(figure){margin:…}` sem
  camada) **têm de ficar fora do `@layer`**. É o caso do alinhamento de mídia, e
  há um **teste-guarda** que falha se essas regras voltarem para dentro de uma
  camada.

**Alternativa descartada.** Tirar tudo das camadas: perderíamos a ordem interna
previsível e trocaríamos 3 `!important` documentados por uma guerra de
especificidade difusa.

---

## ADR-008 — O tema não emite SEO; faz ponte para o plugin ativo

**Contexto.** O site tem plugin de SEO (hoje Rank Math, antes Yoast). Duas fontes
emitindo `<title>`, meta description, Open Graph ou JSON-LD produzem duplicidade.

**Decisão.** O tema **não emite nada de SEO**. Só chama o breadcrumb do plugin
ativo, por um único ponto (`Arena\Seo`), com ordem de preferência
Rank Math → Yoast → SEOPress → nada.

**Consequências.**

- Trocar de plugin de SEO volta a ser trocar um plugin.
- **Não existe breadcrumb próprio do tema**, de propósito: o plugin também emite
  o JSON-LD `BreadcrumbList` correspondente, e uma trilha visual que não bate com
  a estruturada é pior do que trilha nenhuma.
- Sem provedor, nem o `<nav>` é impresso — marco de navegação vazio é ruído para
  leitor de tela.
- Um teste impede que qualquer template volte a chamar o plugin direto.

Histórico do bug que motivou isso:
[Erro 2](11-diario-de-bordo.md#erro-2--amarrei-o-breadcrumb-a-um-plugin-específico).

---

## ADR-009 — Valor vazio significa "padrão do tema"

**Contexto.** Um tema com opções corre o risco de, ao ser atualizado, **mudar a
aparência de um site em produção** só porque passou a emitir valores para
variáveis que antes não existiam.

**Decisão.** Opção vazia ⇒ **a variável CSS não é emitida**. Cada regra do
`main.css` usa `var(--token, valor-de-hoje)`.

**Consequências.**

- Atualizar o tema **não muda um pixel** até alguém escolher algo
  explicitamente. Os fallbacks foram conferidos no CSS compilado.
- Números fora da faixa declarada são **descartados**, não truncados — para que
  um valor editado à mão no banco não vire `--arena-site-width: 99999px` no HTML.
- Opções que agem no PHP (itens por bloco, cards por linha, imagem padrão, faixa
  escura) entram só como **padrão**: o atributo escrito no bloco do WPBakery
  vence, porque é a escolha específica daquela seção da home.

---

## ADR-010 — Acessibilidade derivada, não conferida na mão

**Contexto.** O dono do site pode escolher qualquer cor de destaque. Uma cor
clara sobre branco produz contraste ilegível — e ninguém vai calcular WCAG antes
de escolher.

**Decisão.** O tema **deriva** a cor de texto acessível a partir do destaque
escolhido, escurecendo em passos de HSL até atingir ≥ 4,5:1.

**Consequências.**

- `#ffcc00` (1,51:1 sobre branco) vira `#8a6e00` (**4,87:1**) para texto;
  `#00aa55` vira `#008643` (**4,68:1**). O destaque escolhido continua sendo usado
  onde é fundo.
- A derivação tem teste unitário, inclusive para entrada malformada.
- Vale igual no Customizer e no painel do ACF — não há caminho de configuração
  que escape da garantia.

---

## ADR-011 — Contornos de plataforma moram no tema (por falta de opção)

**Contexto.** Dois defeitos reais de produção não são do tema: a rota REST
`/batch/v1` removida por um mu-plugin da hospedagem, e a cadeia de dependências
do wpDiscuz arrastando o editor de posts para a tela de widgets. O lugar certo
para corrigi-los seria um mu-plugin — mas `wp-content/mu-plugins` é um symlink
para a árvore gerenciada, propriedade do root.

**Decisão.** Concentrar os contornos em `Arena\Compatibility`, com três regras:

1. **escopo mínimo** — só a tela afetada, só handles de `wp-content`;
2. **condicional ao sintoma** — o contorno da rota REST só age **quando a rota
   está de fato ausente**, então se desfaz sozinho no dia em que a hospedagem
   liberar;
3. **comentário com a medição** que o motivou, para que alguém possa remover o
   código com segurança quando a causa sumir.

**Consequências.** O tema carrega código que conceitualmente não é dele — e isso
é registrado como **débito conhecido**, não como arquitetura desejada. Detalhes
em [09 — Infraestrutura](09-infraestrutura-producao.md).

---

## ADR-012 — Validar em produção por preview com token, sem staging

**Contexto.** Não havia ambiente de *staging*, e várias coisas (Yoast/Rank Math,
wpDiscuz, anúncios, cache) só se comportam de verdade com conteúdo e plugins
reais.

**Decisão.** Um mu-plugin opcional que troca o tema **por requisição**, mediante
token secreto definido no `wp-config.php`.

**Consequências.**

- Dá para ver o tema novo no servidor real, com conteúdo real, **enquanto os
  visitantes continuam no tema antigo**.
- Endurecido de propósito: token tem de existir, ser string e não ser vazio;
  comparação com `hash_equals`; entrada em forma de array resulta em `null`
  (falha fechada); resposta com `X-Robots-Tag: noindex, nofollow` e `no-cache`
  para o preview não ser indexado nem cacheado.
- **Não está instalado em produção**, por escolha: uma vez que o Arena está
  ativo, ele faria todo administrador logado ver o outro tema. Ver
  [14 — Runbook](14-runbook-operacional.md#preview-por-token-quando-usar-e-quando-não).

---

## ADR-013 — Customização no tema filho

**Decisão.** `arena-child` existe e é o tema **ativo** em produção.

**Consequências.** Atualizações do tema pai não sobrescrevem customizações. Em
troca, é preciso lembrar que o estilo do filho **depende** do handle
`arena-main` — se o manifest do Vite faltar e `arena-main` não for registrado, o
WordPress **descarta silenciosamente** o estilo do filho. Foi um dos dois
Críticos da revisão, e é o motivo do fallback para `style.css`.
