# 07 — Opções e painel de estilização

## Onde o dono do site configura o tema

| Tela | Caminho | O que tem |
|---|---|---|
| **Painel próprio** | menu **Arena** na barra lateral do admin | todas as opções, em abas |
| **Customizer** | *Aparência → Personalizar → Arena* | as mesmas opções, com pré-visualização ao vivo |
| **Logo** | *Aparência → Personalizar → Identidade do site* | `custom-logo` nativo do WordPress |
| **Painel ACF** | menu "Arena" (só com **ACF PRO**) | alternativa opcional, mesmos 4 campos antigos |

As duas primeiras escrevem no **mesmo** `theme_mod`. Não há sincronização a
fazer nem valor divergente possível.

## A regra que sustenta tudo: vazio = padrão do tema

> Campo vazio significa "usar o padrão do tema", e nesse caso **a variável CSS
> não é emitida**. Cada regra do `main.css` usa `var(--token, valor-de-hoje)`.

Consequência prática: publicar uma versão nova do tema num site em produção **não
muda um pixel** até alguém escolher algo. E apagar o campo volta ao original.
Verificado no deploy: com nada configurado, `Settings::cssTokens()` devolve
array vazio e o HTML não recebe override nenhum.

## Os quatro grupos

### Cores
| Campo | Padrão | Variável CSS |
|---|---|---|
| Fundo da barra superior | `#0b0b0b` | `--arena-topbar-bg` |
| Fundo do cabeçalho | `#0b0b0b` | `--arena-header-bg` |
| Cor dos itens do menu | `#ffffff` | `--arena-menu-color` |
| Cor do menu no hover | acento | `--arena-menu-hover` |
| Fundo do rodapé | `#1b1b1b` | `--arena-footer-bg` |
| Cor do texto do rodapé | `#cccccc` | `--arena-footer-text` |

> O fundo do cabeçalho é **load-bearing**: a logo tem letras claras. Um fundo
> claro aqui deixa a marca ilegível — foi exatamente o bug relatado como "logo
> invisível" no começo do projeto.

### Tipografia
| Campo | Faixa | Variável CSS |
|---|---|---|
| Tamanho base do texto | 13–22 px | `--arena-font-size-base` |
| Altura de linha | 1.3–2.0 | `--arena-line-height` |
| Peso dos títulos | 500–900 | `--arena-headline-weight` |
| Tamanho do título da matéria | 22–56 px | `--arena-post-title-size` |

### Layout e largura
| Campo | Faixa | Onde age |
|---|---|---|
| Largura da caixa de conteúdo | 960–1600 px | `--arena-site-width` |
| Espaço entre blocos | 16–96 px | `--arena-block-spacing` |
| Posição da sidebar | direita / esquerda / sem sidebar | `Arena\Layout` |
| Cards por linha | padrão do bloco / 2 / 3 / 4 | padrão para os blocos |

### Blocos e listagens
| Campo | Efeito |
|---|---|
| Cor dos títulos de bloco | `--arena-block-title-color` |
| Faixa escura em "Últimas notícias" | liga/desliga o esquema escuro |
| Itens por bloco | padrão de `count` |
| Imagem padrão dos cards | usada quando o post não tem destaque |

## Como adicionar uma opção

**Só mexa em `inc/Settings.php`.** As duas telas se montam a partir do esquema.

```php
'arena_minha_opcao' => [
    'group'       => 'cores',              // cores | tipografia | layout | blocos
    'label'       => 'Minha opção',
    'description' => 'O que ela faz, em uma frase.',
    'type'        => 'color',              // color|select|number|checkbox|image|text
    'default'     => '#000000',            // referência mostrada, não valor forçado
    'css_var'     => '--arena-minha-var',  // opcional
    // number: 'min', 'max', 'step', 'unit'
    // select: 'choices' => ['valor' => 'Rótulo']
],
```

Depois: consuma no CSS com fallback igual ao comportamento atual.

```css
.meu-seletor { color: var(--arena-minha-var, #000000); }
```

Um teste (`SettingsTest`) verifica que **toda** variável declarada é realmente
lida por alguma regra do `main.css`. Opção que não faz nada é pior que opção
inexistente: o dono do site mexe e conclui que o tema está quebrado.

## Saneamento

Todo valor passa por `Settings::sanitize()`, por tipo:

| Tipo | Regra |
|---|---|
| `color` | hex normalizado; aceita sem `#`; qualquer outra coisa vira "não configurado" |
| `select` | precisa estar nas `choices` |
| `number` | fora da faixa é **descartado**, não truncado |
| `checkbox` | `1` ou `0` |
| `image` | ID de anexo positivo |

**Número fora da faixa é descartado de propósito.** Se alguém editar o banco à
mão e colocar `99999` na largura, o valor não vira
`--arena-site-width: 99999px` — vira "não configurado", e o CSS mantém o padrão.

Duas armadilhas de PHP que os testes pegaram e que valem lembrar:

- **Chaves numéricas de array viram inteiro.** `'800' => 'Rótulo'` tem chave
  `800` (int). Comparar com `in_array('800', array_keys(...), true)` falha. A
  solução no código é `array_map('strval', array_keys(...))`.
- **Checkbox desmarcado não aparece no POST.** Precisa ser interpretado como
  `0`, não como "não enviado" — senão desmarcar nunca salva.

## Precedência de valores

```
theme_mod  (painel do admin ou Customizer)
    ↓ se vazio/inválido
opção do ACF   (só se ACF PRO estiver ativo)
    ↓ se vazio/inválido
padrão do tema
```

Implementado em `Arena\Options` (para as 4 opções históricas) e em
`Arena\Settings::value()` (para o esquema novo).

## Contraste acessível derivado automaticamente

`Options::accessibleTextColor()` recebe a cor de destaque escolhida e devolve uma
variante que passa **WCAG AA** (4.5:1) contra branco, escurecendo em passos de
luminosidade HSL até atingir a razão — com margem (o alvo interno é 4.6:1).

Isso protege a acessibilidade de uma escolha inocente: um acento claro deixaria
badges e links de autor abaixo do mínimo legível. Exemplo verificado: `#00aa55`
deriva `#008643` (4,68:1).

Contexto: uma auditoria minha teve **ponto cego** aqui — rodei o Lighthouse só em
mobile numa matéria, onde o menu de desktop está `display:none` e não há
paginação. Duas superfícies persistentes com 4,02:1 passaram batidas. Depois de
corrigir, a verificação passou a incluir **desktop em página 2 de categoria**.
Moral: escolha o par viewport/template pensando em onde o elemento aparece.

## Por que existe painel próprio em vez de só ACF

O painel original era uma página de opções do ACF. Páginas de opções são recurso
do **ACF PRO**, e a produção tinha a versão gratuita — então
`OptionsPanel::boot()` saía na primeira linha e **nenhum menu era criado**. O
dono do site ficou sem encontrar as configurações, com razão.

O painel atual (`Arena\AdminPanel`) não depende de plugin nenhum: `add_menu_page`,
`admin_post` para gravar, nonce por aba, capacidade `edit_theme_options`, e
saneamento pelo esquema. O painel do ACF continua existindo como alternativa
para quem tiver o PRO.

Registro histórico completo em [ADR-004](12-decisoes-arquiteturais.md) e no
[diário de bordo](11-diario-de-bordo.md#erro-4--painel-de-opções-que-nunca-apareceu).
