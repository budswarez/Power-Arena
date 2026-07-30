# 06 — Blocos e shortcodes

## O problema que estes blocos resolvem

A home do pichauarena é uma página montada no **WPBakery**, e o conteúdo dela
usa shortcodes do Publisher:

```
[bs-modern-grid-listing-7 …]
[bs-mix-listing-3-1 …]
[bs-blog-listing-1 …]
[bs-grid-listing-1 …]
```

Se o tema novo não registrasse essas tags, a home ficaria com os shortcodes
aparecendo como texto cru no dia da troca — e a redação teria que remontar tudo.

A decisão foi **reimplementar os quatro blocos com as mesmas tags e atributos**
(ver [ADR-002](12-decisoes-arquiteturais.md)). Resultado: a home existente
continua funcionando sem edição, e a redação continua montando a home no editor
visual como antes, porque os blocos também são registrados via `vc_map()` e
aparecem no painel do WPBakery na categoria "Arena".

## Os quatro blocos

| Shortcode | Layout | Uso na home |
|---|---|---|
| `bs-modern-grid-listing-7` | destaque grande + tiles menores | bloco de destaques no topo |
| `bs-mix-listing-3-1` | um card grande + lista compacta | colunas de categoria |
| `bs-blog-listing-1` | lista vertical com resumo | bloco de leitura |
| `bs-grid-listing-1` | grade uniforme | "Últimas notícias" |

## Atributos aceitos

Comuns a todos:

| Atributo | Padrão | O que faz |
|---|---|---|
| `count` | `5` | quantos posts (limitado entre **1 e 50**) |
| `columns` | `4` | colunas na grade |
| `category` | — | IDs de categoria, separados por vírgula |
| `tag` | — | IDs de tag |
| `post_ids` | — | posts específicos |
| `offset` | — | pula os N primeiros |
| `order` | `DESC` | direção |
| `order_by` | — | critério de ordenação |
| `time_filter` | — | recorte temporal |
| `ignore_sticky_posts` | `1` | ignora posts fixados |
| `title` | — | texto do heading do bloco |
| `hide_title` | `0` | esconde o heading mesmo com `title` preenchido |
| `heading_color` | — | cor da tarja do heading |
| `bs-text-color-scheme` | — | `light` = texto claro, ou seja **faixa de fundo escuro** |
| `disable_duplicate` | `0` | exclui posts que blocos anteriores já mostraram |
| `bs-show-desktop` / `bs-show-tablet` / `bs-show-phone` | `1` | visibilidade por breakpoint |

Três comportamentos merecem atenção porque foram descobertos medindo a
referência, não lendo documentação:

**`bs-text-color-scheme="light"` significa fundo escuro.** O nome se refere à cor
do *texto*. O Publisher deixa o atributo vazio para seções claras.

**`heading_color` colore a barra, não o texto.** Na referência, o texto do
heading é sempre branco sobre a tarja colorida. Por isso o `style="color:…"` fica
no `<div class="section-heading">` (para o `::before` ler `currentColor`) e o
texto é forçado a branco por regra própria.

**Não existe cor de badge por categoria.** Uma hipótese inicial foi que cada
categoria tinha cor própria nos badges dos cards. Medindo o CSS da referência:
não existe. Os badges usam o acento único; cores por seção só valem para os
headings de bloco.

## `disable_duplicate`: o detalhe que quase virou "regressão"

O `Renderer` mantém uma lista estática dos posts já renderizados na requisição.
Um bloco com `disable_duplicate="1"` exclui essa lista via `post__not_in`.

Ao implementar, uma revisão apontou "paridade quebrada: o bloco de últimas
repete posts do destaque". Medindo a referência pública: **ela repete também** —
porque aquele bloco tem `disable_duplicate="0"`. O comportamento estava certo; o
que faltava era o atributo ser respeitado.

Lição registrada: paridade se confere na referência, não na expectativa.

## Padrões globais versus atributo do bloco

Três opções do painel do tema atuam como **padrão** para os blocos:

| Opção (painel) | Vale quando |
|---|---|
| Itens por bloco | o bloco não define `count` |
| Cards por linha | o bloco não define `columns` |
| Faixa escura em "Últimas notícias" | desligada, neutraliza `bs-text-color-scheme="light"` |

**O atributo escrito no bloco sempre vence**, porque é a escolha específica
daquela seção da home. A faixa de `count` (1–50) continua valendo para qualquer
origem — nem o painel consegue pedir 500 posts.

## `[accordions]` / `[accordion]`

Presente em cerca de 96% das matérias (a caixa "Resumo da matéria"). A
reimplementação usa `<details>`/`<summary>` nativos: sem JavaScript, acessível
por teclado, e o conteúdo fica no HTML (indexável) mesmo fechado.

## `[vc_single_image]` com heading

`Blocks\SingleImageHeading` trata o caso da referência em que uma imagem vem
acompanhada do heading colorido acima — usado no banner da home.

## Adicionando um layout novo

1. Crie `template-parts/listing/meu-layout.php` (recebe `$args['query']` e
   `$args['options']`).
2. Adicione a chave em `Renderer::LAYOUTS`.
3. Registre o shortcode em `Blocks\Shortcodes` (se for uma tag nova).
4. Se precisar aparecer no editor visual, adicione o mapeamento em `Blocks\VcMap`.
5. Teste em `tests/Listing/` — o mínimo é: renderiza, respeita `count`, reseta o
   postdata.

Reaproveite os cards de `template-parts/card/` antes de criar um formato novo.
