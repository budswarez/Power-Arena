# 10 — Medições e performance

Todo número deste projeto tem procedência. Este documento diz **quanto**,
**como foi medido** e **o que o número não prova**.

> **Regra que vale para tudo aqui:** medição sem método é opinião. Se você
> precisar citar um destes números para alguém, cite junto a condição em que ele
> foi obtido.

---

## Peso dos assets do tema

Medido no diretório de build, com `ls`/`du` sobre os arquivos que o tema
realmente enfileira:

| Arquivo | Bytes | |
|---|---:|---|
| `assets/dist/assets/main-*.css` | 42.483 | 41,5 KB |
| `assets/dist/assets/main-*.js` | 3.545 | 3,5 KB |
| **CSS + JS** | **46.028** | **45 KB** |
| 12 arquivos `.woff2` (Barlow + Oswald) | 229.404 | 224 KB |

Comparação com o tema anterior, medida no site público antes da troca:

| | Publisher | Arena |
|---|---:|---:|
| CSS do tema | **734 KB** | **41,5 KB** |
| Total CSS+JS da página amostrada | ~1.766 KB | — |

O CSS do tema caiu de **734 KB para 41,5 KB** (~18×).

> **Correção de uma afirmação anterior.** Em algum momento este projeto
> registrou "33 KB de assets". Aquele número **excluía as fontes**, porque na
> época elas vinham do Google Fonts e eram consideradas terceiros. Depois que as
> fontes passaram a ser servidas pelo próprio tema, a conta honesta passou a ser
> **45 KB de CSS+JS + 224 KB de fontes**. O Publisher, além dos 734 KB de CSS,
> **também** buscava as mesmas fontes no Google.

Esse é o único bloco de números aqui que é **independente de ambiente**: não
depende de rede, de servidor nem de cache. É o que o tema controla.

---

## Core Web Vitals em produção

Medidos no site real, com Chrome headless controlado por **CDP**
(Chrome DevTools Protocol) via websocket, viewport **1366×768**, perfil novo a
cada execução. Os scripts observam `PerformanceObserver` com `buffered: true`
instalado **antes** da navegação (`Page.addScriptToEvaluateOnNewDocument`), para
não perder entradas anteriores ao primeiro `evaluate`.

| Métrica | Valor | Referência |
|---|---|---|
| **LCP** | **888 ms** | bom < 2,5 s |
| **CLS** | **0,0000** | bom < 0,1 |
| **INP** | **< 16 ms** | bom < 200 ms |

### O que cada número significa exatamente

**LCP — 888 ms.** O script (`cdp_lcp.py`) registra o elemento LCP, o `url`, a
`tag`, `loading`, `fetchpriority`, e junto imprime TTFB, `domContentLoadedEventEnd`,
`loadEventEnd`, os 8 recursos mais pesados, as imagens do primeiro viewport e a
lista de `preload`/folhas de estilo/scripts bloqueantes. Espera **14 s** após a
navegação antes de ler. Cache aquecido.

**CLS — 0,0000.** O script (`cdp_cls.py`) não se contenta com a pintura inicial:
navega, espera 12 s, **rola para 30%, 60% e 100% da página** (3 s em cada
posição), volta ao topo, e só então lê o acumulado — ignorando deslocamentos com
`hadRecentInput`. Ele também lista, quando há deslocamento, **qual elemento**
deslocou e de que retângulo para qual, quantas imagens estão sem `width`/`height`
declarados, e quais iframes/blocos de anúncio ocupam altura sem reservá-la.
`0,0000` significa: nenhum deslocamento registrado nessa sequência.

**INP — < 16 ms.** Aqui a leitura precisa de honestidade. O script
(`cdp_inp2.py`) dispara uma **sequência de interações reais** via
`Input.dispatchMouseEvent`/`dispatchKeyEvent` (abrir busca, abrir menu
off-canvas, `Escape`, acordeão), respeitando as mudanças de estado e pulando o
que não está visível. O observador é registrado com
`durationThreshold: 16`. Portanto:

> **"INP < 16 ms" quer dizer: nenhuma interação da sequência durou o suficiente
> para ser reportada no limiar de 16 ms.** Não é um valor medido de 15 ms — é a
> ausência de qualquer evento acima do limiar. O script também coleta
> *long tasks* em paralelo.

---

## O CSS agregado de 495 KB

Medido em produção: o Autoptimize agrega tudo num arquivo de **495.078 bytes**.
Esse número importa por uma razão específica: **o navegador só descobre os
`@font-face` depois de baixar e parsear esse arquivo**.

Consequência medida (commit `2a06ac1`):

| | Antes | Depois |
|---|---:|---:|
| início do download do Barlow 400 | 313 ms | 313 ms |
| início do download do **Oswald 500** | **~1.162 ms** | **313 ms** |

O Oswald estava fora do `preload`. Com `font-display: swap` isso não bloqueava a
pintura — **trocava a fonte no meio da leitura**, que é pior de perceber e igual
de ruim.

**Critério do preload: uso medido acima da dobra, não completude.** Na home,
acima da dobra: **Barlow 400 em 31 elementos** e **Oswald 500 em 21** (títulos de
card, menu, headings de bloco). Só essas duas faces são pré-carregadas. Os
**outros 10 arquivos** ficam fora de propósito — cada `preload` disputa banda com
o LCP.

Verificações que acompanham essa decisão:

- as declarações `latin-ext` mantiveram o `unicode-range` correto após a
  minificação do Autoptimize (**12 de 12**), então esses subsets só baixam
  quando algum caractere exige;
- os testes cobrem as duas URLs pré-carregadas, **a existência dos arquivos em
  disco** (um `preload` que dá 404 é pior que nenhum) e a saída do `wp_head`.

---

## O ganho de auto-hospedar as fontes

Antes as fontes vinham do Google Fonts — terceiro na rota do LCP, e um dado de
IP de visitante enviado para fora (ângulo LGPD, ver
[15 — Segurança](15-seguranca-e-segredos.md)). Depois da mudança
(commit `94ccb6f`): **zero requisições** a `googleapis`/`gstatic` na home, na
matéria e na categoria.

CLS medido no ambiente local, antes e depois:

| Página | Antes | Depois |
|---|---:|---:|
| matéria | 0,0214 | **0,0054** |
| categoria, página 2 | 0,0193 | **0,0088** |

---

## Lighthouse

| Execução | Performance | Acessibilidade | Boas práticas |
|---|---:|---:|---:|
| matéria, mobile | 88 | **100** | 77 |
| `/category/hardware/page/2/`, desktop | — | **100** | — |

Na execução da matéria: LCP 1,1 s · CLS 0,021 · TBT 160 ms · FCP 1,1 s.

Auditorias que passam: `color-contrast`, `target-size`,
`image-size-responsive`, `html-has-lang`, `image-alt`, `heading-order`,
`link-name`, `button-name`, `meta-viewport`.

### O que esses scores **não** provam

- **Performance 88 é limite do ambiente, não do tema.** No sandbox local o TTFB
  é de ~11 s por causa de I/O de *bind mount* do Docker no Windows. Isolado:
  `admin-ajax` 13,0 s · home com Twenty Twenty-Four 12,5 s · home com Arena
  11,3 s. **O tema é mais rápido que o tema padrão do WordPress no mesmo
  ambiente.** Os números de produção estão na seção anterior.
- **Boas práticas 77** é limitado por `is-on-https` (localhost) e
  `inspector-issues`. Artefatos de ambiente.
- **Um único par viewport/template não cobre o site.** Isso já mordeu: a
  primeira rodada de acessibilidade rodou **só mobile, numa matéria** — onde a
  barra de navegação desktop está `display:none` e não existe paginação. As duas
  superfícies com contraste insuficiente (barra de navegação e número da página
  atual, ambas 4,02:1) estavam **exatamente no ponto cego**. Foram encontradas
  numa revisão de código, não pela auditoria. Ver
  [11 — Diário de bordo](11-diario-de-bordo.md#erro-3--auditoria-com-ponto-cego).

Por isso as duas execuções acima são **mobile+matéria** e
**desktop+categoria paginada**: combinações escolhidas para cobrir o que a outra
não vê.

---

## Contraste: os números que o tema garante

O tema deriva a cor de texto acessível a partir do destaque escolhido, escurecendo
em passos de HSL até atingir ≥ 4,5:1 (com margem, chega a 4,6:1). Valores
verificados:

| Superfície | Contraste |
|---|---:|
| `--arena-accent-text` `#c81f10` sobre branco | 5,74:1 |
| badge (branco sobre destaque acessível) | 5,74:1 |
| meta `#6b6b6b` sobre branco | 5,33:1 |
| destaque `#ffcc00` (1,51:1) → derivado `#8a6e00` | **4,87:1** |
| destaque `#00aa55` → derivado `#008643` | **4,68:1** |

A derivação tem teste unitário, inclusive para entrada malformada.

---

## Suíte de testes

`OK (353 tests, 1511 assertions)` — ver [04 — Testes](04-testes.md) para o que
cada suíte cobre e como rodar.

---

## Como refazer as medições

Os scripts de medição (`cdp_lcp.py`, `cdp_cls.py`, `cdp_inp2.py`) recebem a URL
do websocket do Chrome e a URL da página:

```bash
# 1) Chrome headless com a porta de depuração aberta, perfil limpo
chrome --headless=new --remote-debugging-port=9222 --user-data-dir=<perfil-novo>

# 2) pegar o webSocketDebuggerUrl do alvo da PÁGINA (não do browser)
curl -s http://127.0.0.1:9222/json

# 3) medir
python cdp_lcp.py "<ws://…/devtools/page/…>" "https://www.pichauarena.com.br/"
python cdp_cls.py "<ws://…>" "<url>" 1366
python cdp_inp2.py "<ws://…>" "<url>" 1366
```

> **Erro já cometido aqui:** conectar no alvo do **browser** em vez do alvo da
> **página**. Nesse caso os `Runtime.evaluate` não veem a página, e o script
> imprime "0 deslocamentos" sem ter medido nada. Confira que o
> `webSocketDebuggerUrl` escolhido é o de `"type": "page"`.
