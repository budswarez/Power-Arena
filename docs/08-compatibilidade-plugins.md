# 08 — Compatibilidade com plugins

Como o Arena convive com os plugins que já estavam no site. Este documento
existe porque **quase todo problema relatado pelo dono do site até hoje foi de
fronteira** — tema e plugin, cada um correto sozinho, quebrados juntos.

---

## O princípio

O tema preserva os pontos de extensão do WordPress e não tenta assumir o que é
de plugin:

- `wp_head()` e `wp_footer()` são chamados, sem filtragem do que outros
  imprimem lá;
- o loop principal (`$wp_query`) é usado nos arquivos de listagem, então
  paginação, filtros de query e plugins que se penduram no loop continuam
  funcionando;
- nenhuma tag de SEO, nenhum cache, nenhuma conversão de imagem, nenhum anúncio
  é responsabilidade do tema (ver [01 — Visão geral](01-visao-geral.md#o-que-o-tema-deliberadamente-não-faz)).

Ajustes específicos só entram **quando medidos**, e ficam concentrados em
`Arena\Compatibility` com o comentário explicando o caso real que os motivou.

---

## Quadro geral

| Plugin | Papel | O que o Arena faz |
|---|---|---|
| **Rank Math** | SEO, breadcrumb, sitemap, `llms.txt` | chama o breadcrumb via `Arena\Seo`; não emite SEO próprio |
| **WPBakery** (`js_composer`) | monta a home | fornece os 4 blocos `[bs-*]` mapeados no editor; 3 `!important` para vencer o CSS dele |
| **wpDiscuz** | comentários | `comments_template()` padrão + contorno para a tela de widgets |
| **LiteSpeed Cache** | cache de página, WebP, TTL | nada — o tema não cacheia |
| **Autoptimize** | agrega/minifica CSS e JS | nada, mas a agregação é o motivo do `preload` de fontes |
| **EWWW** | converte imagens | nada; o tema emite `<img>` real com `srcset`/`sizes` |
| **Ad Inserter** | anúncios | nada — os hooks de conteúdo estão preservados |
| **ACF** (opcional) | painel alternativo | lido como 2ª fonte de opções, se estiver ativo |
| **Yoast** | ex-plugin de SEO | suportado como fallback; tabelas já removidas do banco |

---

## Rank Math — e a armadilha do breadcrumb

**O que aconteceu.** O tema chamava `yoast_breadcrumb()` diretamente em sete
templates. O site trocou o Yoast pelo Rank Math e a trilha **desapareceu de
todas as páginas** — sem erro, sem aviso, sem nada no log. Cada template tinha
um `function_exists()` na frente, então simplesmente pulava a chamada. E o Rank
Math **não insere breadcrumb sozinho**: ele avisa no painel que o tema precisa
chamar a função dele.

**A correção** (`inc/Seo.php`, commit `f68fe45`) é um único ponto que descobre o
provedor disponível:

```
Rank Math → Yoast → SEOPress → nada
```

Dois detalhes que valem lembrar antes de mexer nesse arquivo:

- **O Yoast é lido por captura de saída** (`ob_start()`), não por
  `yoast_breadcrumb('', '', false)`. O 3º parâmetro existe no plugin real, mas
  não em toda versão — e uma chamada que devolvesse `null` viraria trilha vazia
  sem ninguém perceber. Capturar a impressão funciona nas duas formas.
- **Sem provedor, nem o wrapper `<nav>` é impresso.** Um marco de navegação
  vazio é ruído para leitor de tela, não economia de código.

**Ponto de extensão.** O filtro `arena_breadcrumb_html` permite ao tema filho
substituir ou suprimir a trilha (devolver `''` suprime) sem tocar em template
nenhum:

```php
add_filter('arena_breadcrumb_html', fn (string $html): string => $html);
```

**Invariante protegida por teste.** Existe um teste que falha se qualquer
template voltar a chamar a função de um plugin de SEO diretamente. Foi essa
regressão que causou o bug; o teste é o que impede que ela volte.

**Diagnóstico rápido.** `Arena\Seo::breadcrumbProvider()` devolve
`rank-math`, `yoast`, `seopress` ou `nenhum`. Em produção respondeu `rank-math`,
renderizando "Início › LoL › \<título da matéria\>".

> **Se a trilha desaparecer de novo:** rode `breadcrumbProvider()`. Se devolver
> `nenhum`, o problema é o plugin (desativado ou com o módulo de breadcrumb
> desligado), não o tema.

---

## WPBakery — a cascata é o problema, não a especificidade

O WPBakery é **obrigatório**: a home é um layout dele. O tema fornece os quatro
blocos de listagem que a home usa, mapeados no editor visual sob a categoria
"Arena" — ver [06 — Blocos e shortcodes](06-blocos-e-shortcodes.md).

O ponto que custa tempo é o CSS. A folha do Arena vive dentro de
`@layer`, e **uma declaração sem camada vence qualquer declaração dentro de
`@layer` na mesma origem, independentemente da especificidade**. O
`js_composer.min.css` não usa camadas e é carregado depois. Consequência
prática:

- os **3 `!important` vivos** do tema existem por isso, todos contra
  `.vc_col-has-fill` (o padding de coluna preenchida). Estão comentados no
  `main.css` explicando o motivo;
- foi a mesma causa de um bug real: as regras de alinhamento de mídia
  (`aligncenter`/`alignleft`/`alignright`) não funcionavam porque o core emite
  `:where(figure){margin:0 0 1em}` **sem camada**. A caixa encolhia para a
  largura da imagem, mas as duas margens computavam `0px` — imagem colada à
  esquerda. A correção foi **tirar essas regras do `@layer`**, não aumentar
  especificidade (commit `eb35577`). Há um teste-guarda que percorre a folha
  mantendo uma pilha de blocos abertos e falha se um seletor de alinhamento
  aparecer dentro de `@layer`.

**Precedência de atributos.** Quando uma opção do painel e um atributo do bloco
dizem coisas diferentes, **o atributo do bloco vence** — é a escolha específica
daquela seção da home. As opções do painel entram apenas como padrão. Ver
[07 — Opções e painel](07-opcoes-e-painel.md).

---

## wpDiscuz — e a tela de widgets que não salvava

Do lado dos comentários não há nada de especial: o tema chama
`comments_template()` e o `comments.php` é padrão (inclusive o campo de consentimento
de cookies do core, que uma versão do tema chegou a derrubar ao sobrescrever
`fields`).

O caso real foi outro. **A tela `Aparência → Widgets` ficava inutilizável**: ao
salvar aparecia *"Ocorreu um erro. Cannot read properties of undefined (reading
'0')"*, e o console mostrava `Store "core/interface" is already registered`
disparado pelo `edit-widgets.min.js`.

Causa medida: o wpDiscuz registra o script de bloco dele
(`wpdiscuz-inline-feedback-button-js`) declarando `wp-edit-post` como
dependência, **para todos os editores de blocos**. A cadeia

```
wpdiscuz-inline-feedback-button-js → wp-edit-post → wp-editor
```

fazia o `editor.min.js` carregar junto do `edit-widgets.min.js`. No WP 7.0 os
dois embutem a store `core/interface` (o handle `wp-interface` deixou de
existir), a segunda tentativa de registro lança, a inicialização do editor
aborta no meio e a gravação morre lendo `[0]` de algo que nunca foi construído.

`Compatibility::keepPostEditorOutOfWidgetScreens()` remove da fila os scripts
que alcançam esses pacotes, com três limites deliberados:

- **só nas telas de widgets** (`widgets.php`, `customize.php`);
- **só handles de plugin/tema** (`src` dentro de `wp-content`). Handles do core
  nunca são removidos — remover às cegas poderia derrubar o próprio
  `wp-edit-widgets`;
- **busca recursiva** na árvore de dependências. No caso real o pacote estava a
  **dois** níveis de distância, e checar só as dependências diretas não achava
  nada — foi o primeiro diagnóstico, errado.

Roda em `admin_enqueue_scripts` com prioridade `PHP_INT_MAX`: antes disso não há
o que remover, porque os plugins ainda não enfileiraram.

> **Não é bug do tema.** Reproduzia igual com o Publisher ativo. O lugar certo
> para esse contorno seria um mu-plugin, mas `wp-content/mu-plugins` pertence ao
> root nesta hospedagem (ver [09 — Infraestrutura](09-infraestrutura-producao.md#mu-plugins-não-é-seu)),
> então o tema é o único lugar gravável. Quando o plugin corrigir a dependência,
> o método pode sair.

Há um segundo contorno na mesma tela, por outra causa — a rota REST
`/batch/v1` bloqueada pela hospedagem. Está descrito em
[09 — Infraestrutura de produção](09-infraestrutura-producao.md#a-rota-batchv1-é-removida-pela-plataforma),
porque a origem é a plataforma, não o plugin.

---

## LiteSpeed Cache e Autoptimize — quem faz o quê

Os dois estão ativos e **se sobrepõem**. Vale saber quem está no controle antes
de investigar qualquer coisa de performance:

- **LiteSpeed**: cache de página, TTL público, WebP na entrega, modo visitante.
- **Autoptimize**: agrega e minifica CSS e JS.

O tema não faz nada disso, e essa divisão tem uma consequência direta: a
agregação do Autoptimize produz **um CSS de ~495 KB** (medido em produção, ver
[10 — Medições](10-medicoes-e-performance.md#o-css-agregado-de-495-kb)) que
precisa ser baixado e parseado antes de o navegador descobrir qualquer
`@font-face`. É exatamente por isso que o tema **pré-carrega** as duas faces
usadas acima da dobra em vez de confiar na descoberta pelo CSS.

Verificado: a minificação do Autoptimize **preserva** os `unicode-range` das 12
declarações `@font-face`, então os subsets `latin-ext` continuam só baixando
quando algum caractere exige.

**Ordem de limpeza de cache** (importa): LiteSpeed *Purge All* → Autoptimize
*Delete Cache* → CDN, se houver. Ver [14 — Runbook](14-runbook-operacional.md).

---

## EWWW e imagens

O tema emite `<img>` real com `srcset` e `sizes` — não o mecanismo proprietário
de *lazy background* do Publisher. O EWWW converte, o LiteSpeed entrega.

Um detalhe do WordPress 7 que já quebrou a home: o core reprepende `auto,` ao
atributo `sizes` em `wp_filter_content_tags()`, e com a imagem dentro de um
`position:absolute` isso fazia um dos cinco tiles do mosaico **não pintar**. O
tema emite `sizes` explícito para o tamanho `arena-card` e desliga
`wp_img_tag_add_auto_sizes` **apenas nas páginas com hero** — não no site todo,
como fazia a primeira versão da correção.

---

## ACF — opcional de verdade

`Arena\Options` resolve nesta ordem:

```
theme_mod (painel Arena / Customizer)  →  ACF (se ativo)  →  padrão do tema
```

O ACF **não está instalado em produção**, e o tema funciona inteiro sem ele. O
painel de opções próprio existe justamente porque a primeira versão dependia de
`acf_add_options_page()`, que é recurso do **ACF PRO** — história em
[07 — Opções e painel](07-opcoes-e-painel.md) e em
[11 — Diário de bordo](11-diario-de-bordo.md#erro-4--painel-de-opções-que-nunca-apareceu).

---

## Yoast — o que sobrou

O Yoast saiu, mas duas coisas ficaram:

- **o fallback no `Arena\Seo`**, porque custa nada e cobre uma volta atrás;
- **as meta descriptions em `_yoast_wpseo_*` foram preservadas** no banco. As
  *tabelas* do Yoast (`wp_yoast_indexable` e as outras sete) foram removidas com
  backup, porque o plugin estava inativo. Os metadados por post, não — eles são
  conteúdo.

As **743 regras de redirecionamento do Yoast Premium** são um caso aberto: parte
delas o WordPress já resolve sozinho via `_wp_old_slug`, parte não. Está em
[13 — Pendências](13-pendencias.md#redirecionamentos-do-yoast-premium).
