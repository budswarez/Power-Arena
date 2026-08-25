# 05 — Build e assets

## Como funciona

O tema **não** enfileira arquivos por caminho fixo. Ele lê o manifest gerado
pelo Vite e usa os nomes com hash que estão lá:

```
assets/src/js/main.js      ← fonte JS (283 linhas, ES6+, sem jQuery)
assets/src/css/main.css    ← fonte CSS (3.153 linhas, em camadas)
        │
        │  npm run build  (Vite 5)
        ▼
assets/dist/assets/main-<hash>.js
assets/dist/assets/main-<hash>.css
assets/dist/.vite/manifest.json   ← Arena\Assets lê ESTE arquivo
```

`vite.config.js`:

```js
base: '/wp-content/themes/arena/assets/dist/',   // URL fixa, sem depender do host
build: { manifest: true, outDir: 'assets/dist', emptyOutDir: true }
```

O `base` fixo é o que permite o mesmo build funcionar em qualquer domínio sem
reescrita de URL.

## Comandos

```bash
npm run dev      # watch: recompila ao salvar (útil com o wp-env de pé)
npm run build    # produção: minifica, gera hash, escreve o manifest
```

O hash no nome do arquivo **é** a estratégia de cache-busting. Por isso o tema
enfileira com versão `null` — o nome já muda quando o conteúdo muda.

## ⚠️ O manifest é um arquivo oculto

```
assets/dist/.vite/manifest.json
```

A pasta começa com ponto. **Muitos clientes de FTP não enviam arquivos ocultos
por padrão** — o FileZilla inclusive. Se o manifest não chegar ao servidor, o
tema perde CSS e JS.

Há uma proteção: sem manifest, `Arena\Assets` registra um handle
`arena-main` apontando para `style.css`. O site abre, mas sem o layout correto.
Isso existe por um motivo concreto: numa revisão, a ausência do manifest fazia o
tema **não registrar handle nenhum**, e como o tema filho declara
`['arena-main']` como dependência, o WordPress descartava silenciosamente o CSS
do filho também. Duas folhas de estilo desaparecendo por causa de um arquivo
oculto.

Formas seguras de publicar: usar o `.zip` do `bin/package.sh` (que **falha** se
o manifest não estiver lá) ou o `bin/deploy.sh` por SSH.

## CSS: camadas e a exceção importante

O `main.css` usa `@layer base, components, utilities`. Isso dá previsibilidade:
componente vence base sem depender de ordem de arquivo.

**A exceção deliberada:** o bloco de alinhamento de mídia (`.aligncenter`,
`.alignleft`, `.alignright`, `.alignwide`, `.alignfull`) fica **fora** de
qualquer camada. O motivo, medido no site real:

> O WordPress core emite `:where(figure){ margin: 0 0 1em }` **sem** layer. Na
> cascata de camadas, declaração sem layer vence qualquer declaração dentro de
> layer, **independentemente de especificidade**. Com o bloco dentro de
> `@layer components`, o `margin-inline: auto` do `.aligncenter` simplesmente
> não era aplicado (computava `0px`) e a imagem ficava encostada à esquerda.

Há um teste (`StyleGuardTest`) que falha se esse bloco voltar para dentro de uma
camada. **Não mova sem re-medir.**

Detalhe relacionado: centralizar exige `width: fit-content`, não só
`margin: auto`. A base do tema define `img { display: block; max-width: 100% }`,
então a caixa já ocupa a largura toda e não há sobra para as margens
distribuírem.

## Tokens CSS: o que é configurável

O tema imprime um `<style id="arena-inline-tokens">` no `wp_head` com as
variáveis resolvidas das opções:

```html
<style id="arena-inline-tokens">:root{
  --arena-accent:#f42c1a;
  --arena-accent-text:#e41d0b;
  --arena-font-body:'Barlow', sans-serif;
  --arena-font-head:'Oswald', sans-serif;
  --arena-menu-hover:#eaf427;
}</style>
```

E o `main.css` consome com fallback:

```css
.site-header.header-style-2 { background-color: var(--arena-header-bg, #0b0b0b); }
```

**Regra de ouro:** opção vazia = nenhum token emitido = o valor do `main.css`
continua valendo. É isso que garante que atualizar o tema num site em produção
não mude um pixel até alguém configurar algo. Detalhes em
[07 — Opções e painel](07-opcoes-e-painel.md).

## Fontes

Barlow e Oswald são **servidas pelo próprio tema** (`assets/fonts/`, 12 arquivos
WOFF2, licença SIL OFL incluída em `OFL.txt`). Nenhuma chamada ao Google Fonts —
menos uma conexão externa no caminho do LCP, e nenhum IP de visitante enviado a
terceiros (LGPD).

As `@font-face` ficam no `main.css` com `unicode-range` correto (12 de 12
declarações), então os subsets `latin-ext` só baixam quando algum caractere
exige.

**Duas faces são pré-carregadas**, escolhidas por medição do que aparece acima da
dobra:

| Face | Elementos acima da dobra | Sem preload, começava em |
|---|---|---|
| `barlow-400-latin.woff2` | 31 | 889 ms |
| `oswald-500-latin.woff2` | 21 | 1.162 ms |

Com preload, as duas começam em **313 ms**. As outras 10 faces **não** são
pré-carregadas de propósito: cada preload disputa banda com o LCP, e o critério
é ter uso medido acima da dobra, não completude.

## JavaScript

283 linhas, ES6+, sem dependências, carregado com `defer`. O que ele faz:

- menu off-canvas no mobile (abrir/fechar, submenus em acordeão, foco, `Escape`);
- barra de menu que se fixa no topo ao rolar (com espaçador reservando a altura,
  para não deslocar o layout);
- alternância da busca no cabeçalho;
- nada além disso.

Duas lições que estão no código como comentário:

- O overlay do menu mobile precisa de `:not([hidden])` no CSS. Sem isso, um
  overlay invisível de tela cheia interceptava **todos** os toques — inclusive
  os da busca. Só foi encontrado dirigindo a interação por automação, porque
  visualmente nada aparecia.
- `Escape` fechando a busca exigiu remover uma regra `:focus-within` que
  brigava com o retorno de foco feito pelo JS.

## Empacotamento

```bash
bash bin/package.sh
```

O script:

1. roda `npm run build`;
2. copia **apenas** o que o site precisa em tempo de execução;
3. inclui o tema filho (`arena-child/`);
4. **falha** se `assets/dist/.vite/manifest.json` não existir ou não entrar no zip;
5. **falha** se qualquer pasta de desenvolvimento vazar;
6. gera o `.zip` fora da raiz web.

Nunca vai para o pacote: `node_modules/`, `vendor/`, `tests/`, `docs/`,
`.github/`, `.superpowers/`, `bin/`, `composer.*`, `package*.json`,
`vite.config.js`, `phpunit.xml.dist`, `.wp-env*.json`.
