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

---

# Auditoria de PageSpeed — 30/07/2026

Auditoria completa de velocidade em produção, mobile e desktop.

**Conclusão que orienta tudo o que vem abaixo: o gargalo não está no tema.** O
CSS+JS do Arena é 46 KB de um total de ~1.477 KB (3%). Os itens que dominam são
configuração de cache, terceiros e imagens.

## Método e suas limitações

| Item | Como foi feito |
|---|---|
| Ferramenta | Lighthouse **13.4.1 local** (mesmo motor que o PSI executa) |
| Páginas | home (`/`) e matéria (`/csgo/cs2/`) |
| Modos | mobile (preset padrão) e desktop (`--preset=desktop`) |
| Cabeçalhos/cache | conferidos com `curl -D -` por User-Agent |

**Três limitações declaradas:**

1. **Sem dados de campo (CrUX).** A API do PageSpeed Insights respondeu **429**
   (cota anônima esgotada) nas quatro tentativas. Todos os números aqui são de
   **laboratório**. Para ter dados de usuários reais é preciso uma chave de API
   do PSI/CrUX.
2. **Lighthouse local ≠ PSI.** O throttling é o mesmo, a rota de rede não: o PSI
   mede a partir de um datacenter do Google.
3. **A primeira rodada estava contaminada.** O antivírus **Kaspersky** desta
   máquina injetou **314 KB em 8 requisições**
   (`gc.kis.v2.scr.kaspersky-labs.com`) na página medida. As rodadas finais
   usaram `--blocked-url-patterns=*kaspersky-labs.com*`. **Se você medir daqui e
   vir scripts da Kaspersky, o número não é do site.**

## Resultado

| Cenário | Score | LCP | FCP | TBT | CLS | TTFB |
|---|---:|---:|---:|---:|---:|---:|
| home / **mobile** | **70** | 6,5 s | 3,4 s | 80 ms | **0** | 720 ms |
| home / **desktop** | **98** | 1,1 s | 0,6 s | 0 ms | **0** | 250 ms |
| matéria / mobile | 68 | 7,0 s | 3,0 s | 200 ms | **0** | 700 ms |
| matéria / desktop | 97 | 1,2 s | 0,8 s | 0 ms | **0** | 200 ms |

**Desktop está muito bom. Mobile é o problema.** CLS zero em tudo e TBT baixo —
ou seja, o que dói é **carregamento**, não interatividade nem estabilidade.

## De quem é o peso (home, mobile, 1.477 KB)

| Fatia | Peso | % |
|---|---:|---:|
| Imagens do site | 457 KB | 31% |
| **Google (GTM + GA4)** | **437 KB** | **30%** |
| HTML | 256 KB | 17% |
| Fontes do tema | 153 KB | 10% |
| CSS/JS do site (tema **+ plugins**) | 128 KB | 9% |
| OneSignal | 45 KB | 3% |

> Os 256 KB de HTML são **duas cópias de 128 KB** da mesma página — ver o
> achado nº 1.

## Achado 1 — o Guest Mode carrega a página DUAS vezes

**O maior item isolado.** O Lighthouse registra dois documentos completos, mesma
URL, ambos `200`:

```
doc 1: início 1 ms    → fim 859 ms    128 KB
doc 2: início 1042 ms → fim 1764 ms   128 KB
```

E atribui **3.501 ms** à auditoria *"Avoid multiple page redirects"* — numa URL
que o `curl` prova **não ter redirect nenhum** (`num_redirects=0`, TTFB 145 ms).

A causa está no HTML servido: a lógica de *vary* do **Guest Mode** do LiteSpeed
(`litespeed_vary`, `litespeed_docref` via `sessionStorage`). O Guest Mode entrega
uma cópia genérica e **recarrega a página inteira** quando detecta que o visitante
não tem o cookie de vary — isto é, **em toda primeira visita**, inclusive para
rastreadores.

Opções (`litespeed.conf.*`): `guest = 1`, `guest_optm = 1`.

### ✅ Corrigido em 30/07 — com números

Guest Mode desligado (`guest` e `guest_optm` → 0). O `.htaccess` **não** mudou
(md5 idêntico antes e depois), então o bloco de canonicalização seguiu intacto.

Medição direta por CDP, UA de celular real, cache do navegador desligado:

| | Antes | Depois |
|---|---|---|
| documentos carregados por visita | **2** (o 2º em +920 ms) | **1** |
| `litespeed_vary` no HTML | presente | **ausente** |
| itens renderizados | 29 | 29 (idêntico) |

Lighthouse na home mobile, **3 execuções** para não reportar número de sorte:

| | Antes | Depois (3 runs) |
|---|---|---|
| Score | 70 | **77 – 78** (média 77,7) |
| LCP | 6,5 s | **5,0 – 5,3 s** (média 5,14 s) |
| FCP | 3,4 s | **2,3 s** |
| documentos | 2 | **1** |
| "Avoid multiple page redirects" | 3.501 ms | **0 ms** |

> **A economia real foi menor do que o Lighthouse prometia.** A auditoria
> atribuía **3.501 ms** ao item; o ganho de LCP medido foi de **~1,4 s** (−21%).
> O "wasted ms" do Lighthouse é uma estimativa do custo do recurso, não do efeito
> no LCP — vale ler como ordem de grandeza, não como previsão.

**Terminologia que engana:** o Lighthouse chama isso de *"Avoid multiple page
redirects"*, mas **não havia redirect nenhum** — a contagem de redirects de rede
era **zero**. Era a página inteira sendo baixada duas vezes por JavaScript. Quem
for procurar "redirect" no painel ou no `.htaccess` não acha nada.

## Achado 2 — o cache de página nunca é servido no mobile

Medido com `curl -D -`, três requisições seguidas de cada tipo:

| User-Agent | `X-LiteSpeed-Cache-Control` | Resultado | TTFB |
|---|---|---|---:|
| Android/Chrome (casa a lista mobile) | `no-cache` | **MISS sempre** | 520–710 ms |
| iPhone Safari **sem** o token `Mobile` | `public,max-age=604800` | **HIT** | ~130 ms |
| Desktop | `public,max-age=604800` | **HIT** | ~130 ms |

Ou seja: **todo visitante que casa a lista de UA mobile do LiteSpeed dispara um
render PHP completo.** A configuração parece correta (`cache-mobile = 1`,
`cache-mobile_rules` com `Mobile|Android|…`, TTL 604800), o que aponta para
conflito com o Guest Mode.

**O cache mobile separado não serve para nada neste site:** o Arena é responsivo
e entrega a MESMA marcação para os dois. Comparei o HTML servido a um UA mobile e
a um desktop — 94,6% idêntico, e **as diferenças são todas do próprio LiteSpeed**
(`data-lazyloaded`, script de lazy-load, `litespeed_ui_events`), consequência de
uma cópia estar cacheada e a outra não. Nada vem do tema.

**Ação:** desligar *Separate Mobile Cache*.

> **Honestidade sobre o ganho:** um A/B com o Lighthouse (mesmo throttling, um UA
> que erra o cache e outro que acerta) deu **o mesmo score, 70, e o mesmo LCP de
> 6,5 s**. Ou seja, isto economiza ~400–580 ms de tempo de servidor por
> requisição e **muita carga de PHP** — mas, isolado, não é o que faz o LCP
> mobile ser 6,5 s. O achado 1 é.

## Achado 3 — `http://pichauarena.com.br` cai no wp-login

```
http://pichauarena.com.br/
  → 301 → https://www.pichauarena.com.br/wp-admin/
  → 302 → https://www.pichauarena.com.br/wp-login.php?redirect_to=…%2Fwp-admin%2F
```

Quem digita o domínio sem `www` em `http` **vai para a tela de login**, não para
a home. Vale para links antigos e para rastreadores.

**RESOLVIDO em 30/07.** Ver
[13 — Pendências](13-pendencias.md#http-sem-www-ia-para-wp-admin--resolvido-em-3007)
para a correção aplicada e os números.

**Correção de atribuição:** a primeira versão deste documento dizia que a regra
estava "na plataforma da Hostinger". **Estava errado.** A resposta trazia
`X-Redirect-By: WordPress` e `X-Processing-Time: 0.302` — era o **WordPress**
emitindo o redirect, depois de rodar por inteiro. A pista que confirmou: arquivos
estáticos existentes eram servidos normalmente em `http` sem `www`; só o que
passava pelo WordPress era redirecionado.

Resolvido canonicalizando o host **antes do PHP**, num bloco de `.htaccess` que
não pode entrar em loop. O redirect passou a custar `X-Processing-Time: 0.000`.

As demais variantes já estavam certas: `https://` sem `www` e `http://www` fazem
um único 301 para a canônica.

> ### ⚠️ NÃO crie uma regra de redirecionamento no painel para "corrigir" isto
>
> **A primeira versão deste documento recomendava exatamente isso, e derrubou o
> site.** Uma regra `pichauarena.com.br/ → https://www.pichauarena.com.br` no
> painel da Hostinger causa **loop infinito em todos os caminhos**, porque
> `www.pichauarena.com.br` é **alias de DNS** do domínio sem `www` — a regra casa
> com o próprio `www` e o redireciona para ele mesmo. Como age na camada `hws`,
> derruba até `/robots.txt`. História completa em
> [11 — Diário de bordo](11-diario-de-bordo.md#3007--site-fora-do-ar-por-20-minutos-loop-de-redirect).
>
> **A canonicalização sem-`www` → `www` já funciona sem regra nenhuma** (medido:
> `https://pichauarena.com.br/` → 301 → `https://www.pichauarena.com.br/`).
>
> O que resta é só a variante **`http://` sem `www`**, e ela vem de algo que não
> está nem no `.htaccess`, nem no WordPress, nem nas regras visíveis do painel
> (verificado depois do incidente, com query-busting para descartar cache). O
> caminho aqui é **abrir chamado no suporte da Hostinger**, não experimentar em
> produção.

## Achado 4 — terceiros são 482 KB (33% da página)

| Origem | Peso | Requisições |
|---|---:|---:|
| `googletagmanager.com` (GTM + GA4) | 437 KB | 3 |
| `cdn.onesignal.com` + `api.onesignal.com` | 45 KB | 5 |

O GA4 carrega o `gtag/js` **duas vezes** (160 KB cada) — comportamento normal do
GA4 via GTM, mas são 320 KB. Isso é decisão de negócio, não de tema: se o Site
Kit/GTM/GA4 puderem ser consolidados (GA4 direto, sem GTM), a economia é grande.

## Achado 5 — imagens são a maior fatia (457 KB)

Maior item: o banner da home, `banner-arena-site…@2x.png.webp`, **122 KB**. Já
está em WebP (EWWW + LiteSpeed). O caminho aqui é reduzir a dimensão servida no
mobile e revisar a qualidade de compressão do banner, não trocar de formato.

## Achado 6 — o tema (o que é nosso)

| | |
|---|---|
| CSS | 43,6 KB |
| JS | 3,5 KB |
| Fontes baixadas no mobile | 8 arquivos, 153 KB |

Do lado do tema há **um** ponto a melhorar: das 8 fontes baixadas, **4 são
`latin-ext`** (~67 KB) e **nenhum caractere da página precisa delas** —
verifiquei decodificando as entidades HTML e varrendo as faixas Unicode do
`latin-ext`: zero ocorrências. O CSS servido está correto (12 `@font-face`, todas
com `unicode-range`, só 2 `preload`), e ainda assim o Chrome busca os quatro.

**Não é ganho garantido:** conteúdo de e-sports legitimamente traz nomes com
`latin-ext` (polonês, turco — "Sławomir", "Şahin"), então **remover as faces
quebraria essas matérias**. A investigação certa é descobrir por que o Chrome
ignora o `unicode-range` aqui, não apagar arquivos. Prioridade baixa: 67 KB
contra os 3,5 s do achado 1.

## Prioridade

| # | Ação | Onde | Ganho esperado | Situação |
|---|---|---|---|---|
| 1 | Desligar **Guest Mode** | LiteSpeed | medido: score 70→78, LCP 6,5→5,1 s | ✅ **feito em 30/07** |
| 2 | Desligar **Separate Mobile Cache** | LiteSpeed | −400 a −580 ms de TTFB e carga de PHP | **pendente** — mobile ainda responde `BYPASS` |
| 3 | `http://` sem `www` indo para `/wp-admin/` | `.htaccess` | corrige UX e SEO, −300 ms de PHP | ✅ **feito em 30/07** |
| 4 | Consolidar GTM/GA4 | decisão de negócio | até ~320 KB | aberto |
| 5 | Reduzir o banner da home | mídia | parte dos 457 KB | aberto |
| 6 | Investigar o `latin-ext` | tema | ~67 KB | aberto |

> **Histórico dos itens 1 e 2:** os dois foram aplicados juntos em 30/07 e
> revertidos minutos depois durante a queda do site — que teve **outra** causa (a
> regra do painel). Ficou provado depois que a mudança de opções **não** era a
> causa: restaurar `.htaccess` e as três opções não devolveu o site; só apagar a
> regra devolveu.
>
> O item 1 foi então reaplicado **sozinho**, com backup, verificação de saúde
> antes da medição e três execuções do Lighthouse — resultado acima. O item 2
> segue pendente de propósito: **um ajuste por vez**, para cada número ter uma
> causa só.

**Não faça:** otimizar o CSS/JS do tema. São 3% do peso; o retorno é nulo
comparado aos itens 1 a 3.
