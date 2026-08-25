# 11 — Diário de bordo

História do projeto em ordem, **incluindo os diagnósticos errados**. Isso é
deliberado: saber que uma hipótese já foi testada e descartada evita que alguém
a persiga de novo.

O histórico completo está no `git log` (100 commits) — as mensagens de commit
carregam o "porquê" de cada mudança. Este documento é o mapa.

---

## Linha do tempo

### 24/07 — reconhecimento e núcleo

Reconhecimento do site público **sem tocar no servidor**: leitura do HTML
renderizado e dos valores computados de CSS. Descoberta que reformulou o
projeto: a home é montada em **WPBakery** com **blocos proprietários `[bs-*]`**
do Publisher. Ou seja, o Arena não podia "só escrever templates" — os blocos
usados tinham de ser reimplementados.

Levantamento completo depois: **só a home** usa blocos de builder, e **apenas 4**
tipos de listagem. Posts, categorias, tags e busca usam conteúdo padrão. **O
escopo do problema era muito menor do que parecia.**

Núcleo do tema pronto e verde: autoloader PSR-4, boot, pipeline Vite com
manifest, `Options`, `Compatibility`, preview por token.

### 24/07 — paridade da home

`Arena\Listing\Query` (puro) → `Renderer` → 4 shortcodes `[bs-*]` → `vc_map`
para o editor visual. Cabeçalho, mega-menu, rodapé, `front-page.php`.

Primeira reação do dono do site: **"visual quebrado + códigos aparecendo"**.
Duas causas reais, nenhuma delas de tema:

1. o `js_composer` **não estava** no ambiente local, então `[vc_*]` era impresso
   como texto literal;
2. a faixa do logotipo era branca no Arena, mas no site real é `#0b0b0b` — e o
   logotipo tem o texto **quase branco**. Fundo claro tornava o logotipo
   invisível.

Depois disso, rodadas de paridade visual com valores **medidos** no site de
referência (largura 1200px, ritmo de 50px, `gap` de 2px no mosaico, geometria da
bandeira chanfrada…), verificadas em capturas de tela lado a lado.

### 25/07 — matéria, arquivos, busca

Estrutura de 2 colunas com sidebar à direita, `single.php`, arquivos com
paginação pelo **loop principal** (não pelo `Renderer`, que monta a própria query
e quebraria a paginação), busca, 404, relacionados.

Aqui apareceu uma surpresa dentro do conteúdo: **`[accordions]`/`[accordion]` do
Publisher dentro do corpo dos posts** — em ~96% das matérias, a caixa "Resumo da
matéria". Reimplementado com `<details>`/`<summary>` nativo, zero JavaScript.

### 25/07 — revisão da branch inteira

Revisão de 53 commits / 88 arquivos. Resultado: **2 Críticos, 11 Importantes,
13 Menores** — todos fechados. Os dois críticos:

1. **manifest do Vite ausente ou velho ⇒ zero CSS e JS.** Todos os enfileiramentos
   de estilo estavam dentro de `if ($js !== null)`, e `.vite/` é um diretório que
   começa com ponto — FTP/rsync podem pular. De quebra, o estilo do tema filho
   desaparecia silenciosamente, porque o WordPress ignora enfileiramentos cuja
   dependência nunca foi registrada. Correção: fallback para `style.css`.
2. **`disable_duplicate` nunca implementado.** Detalhe importante em
   [Erro 5](#erro-5--corrigir-para-o-lado-errado-quase).

Nessa rodada as fontes passaram a ser **auto-hospedadas** (saída do Google
Fonts), o contraste foi corrigido de verdade, o menu deixou de duplicar `id`s, e
o preview por token passou a emitir `noindex` + `no-cache`.

### 25/07 — entrega no servidor

`.zip` enviado para um diretório oculto dentro de `themes/`, MD5 conferido local
*versus* remoto, extraído, movido, **diretório e zip apagados**. O
`.vite/manifest.json` (204 bytes) **sobreviveu** — exatamente o risco que o
[DEPLOY.md](../DEPLOY.md) alerta.

Verificado no servidor: `arena` e `arena-child` presentes e **inativos**,
`publisher-child` ainda ativo, `php -l` limpo em todo o PHP do tema sob PHP 8.5.
**Nada mudou para os visitantes** nesse dia.

### 25–26/07 — o tema passa a se configurar sozinho

O dono do site não encontrava as configurações. Isso levou ao Customizer nativo
e, depois, ao **painel "Arena" próprio** — ver
[Erro 4](#erro-4--painel-de-opções-que-nunca-apareceu).

### 26/07 — produção: ativação, limpeza e medição

O tema foi **ativado** (`arena-child`). Na sequência:

- **Rank Math no lugar do Yoast** — e o breadcrumb desapareceu do site inteiro.
  Ver [Erro 2](#erro-2--amarrei-o-breadcrumb-a-um-plugin-específico).
- **Limpeza de banco autorizada**, com backup verificado antes de cada remoção:
  tabela de backup órfã de **287 MB**, tabelas do Yoast, restos de plugins que já
  saíram, `oembed_cache`, transients, drop-in `object-cache.php` órfão.
  Detalhes em [09 — Infraestrutura](09-infraestrutura-producao.md#higiene-do-banco).
- **Duas quebras da tela de widgets** diagnosticadas até a causa raiz: a rota
  REST `/batch/v1` removida pela plataforma, e a cadeia de dependências do
  wpDiscuz arrastando o editor de posts.
- **Medição de Core Web Vitals em produção** e `preload` do Oswald 500 — ver
  [10 — Medições](10-medicoes-e-performance.md).
- Alinhamento de mídia (`aligncenter` e companhia) corrigido — ver
  [Erro 6](#erro-6--especificidade-quando-o-problema-era-a-cascata).

### 26–27/07 — documentação

Esta pasta `docs/`.

### 30/07 — repositório, versionamento e release 0.2.0

Código publicado em `github.com/budswarez/power-arena` (privado), com `CHANGELOG.md`,
política de SemVer ([16 — Versionamento](16-versionamento.md)) e as tags `v0.1.0`
(retroativa) e `v0.2.0`.

**A descoberta que valeu mais que o deploy:** a comparação arquivo por arquivo
entre a máquina local e o servidor mostrou que **produção já rodava o código da
0.2.0 desde 26/07 — rotulado como 0.1.0**. Só 3 de 66 arquivos diferiam, e dois
deles eram a própria string de versão.

Isso é exatamente o problema que o [16](16-versionamento.md#onde-o-número-da-versão-vive)
descreve: o cache-busting dos assets usa a versão do tema, então **subir arquivos
sem subir o número deixa o navegador e o LiteSpeed servindo o CSS antigo**. Não
foi um deploy de funcionalidade — foi corrigir um rótulo errado, o que só se
descobre comparando, não olhando.

Dois erros meus de leitura no caminho, ambos corrigidos por medir de novo:

- concluí que "o `dist` local está desatualizado" porque uma verificação no
  servidor não achou os tokens do painel no CSS. **Estava lendo o arquivo
  errado:** havia dois builds acumulados no servidor e o comando pegou o mais
  antigo por ordem alfabética. O manifest apontava para o correto o tempo todo.
  O build velho foi removido.
- o primeiro pacote saiu com **pai 0.2.0 e filho 0.1.0**, porque o
  `bin/package.sh` leu a cópia legada do tema filho. Ver
  [13 — Pendências](13-pendencias.md#duas-cópias-do-tema-filho). O script ganhou
  um portão que aborta se as versões divergirem.

Também ficou fechada a pendência da instrumentação de memória: o bloco não estava
mais no tema filho, mas o `/tmp/arena-memoria.log` ainda estava no servidor.

**Nota honesta sobre esta release:** a suíte de testes **não pôde ser executada**
(o Docker não estava rodando na máquina). No lugar dela: `php -l` nos 64 arquivos
PHP do pacote sob o PHP 8.5.8 real do servidor (0 erros), conferência de md5 do
pacote local *versus* remoto, comparação byte a byte dos 66 arquivos após a
instalação (idênticos), e verificação do site público — home, matéria com
breadcrumb, categoria paginada e 404. A única mudança de PHP era uma string de
versão.

---

### 30/07 — colunas que não empilhavam no celular

Relato do dono do site, com print: na home, a linha de três colunas
(Hardware / VALORANT / Free Fire) ficava lado a lado no celular, uma palavra por
linha, com conteúdo cortado à direita.

Medido a 390px: três colunas de **130px**, `float:left`, página estourando
**49px**.

**Causa raiz — e não era CSS do tema mal escrito.** A instalação tem
`wpb_js_not_responsive_css = 1` no WPBakery, herdado do Publisher. Isso coloca
`vc_non_responsive` no `<body>`, e o `js_composer.min.css` traz
`.vc_non_responsive .vc_row .vc_col-sm-4{width:33.33%}` **sem media query
nenhum**. As regras responsivas nativas dele continuam lá, em
`@media (min-width:768px)`, mas nunca chegam a importar. O Publisher compensava
com CSS mobile próprio; o Arena não tinha nada equivalente — daí a quebra ter
nascido junto com a troca de tema.

Sequência de verificação, toda na página real antes de escrever código:

1. **hipótese testada por injeção**, não por dedução — o CSS candidato foi
   injetado na home de produção via CDP e medido em **7 larguras**:
   360/390/420/767px empilharam com zero overflow; 768/1024/1366px seguiram com 3
   colunas. Títulos com menos de 120px: **29 antes, 0 depois**;
2. **contraprova de camada**: a MESMA regra dentro de `@layer components` deixou
   as colunas em 130px; fora de camada, foram para 390px. É a ADR-007 outra vez;
3. o CSS **minificado do build** foi testado do mesmo jeito, porque o minificador
   remove as aspas do seletor de atributo (`[class*=vc_col-sm-]`);
4. o teste-guarda foi **quebrado de propósito** (bloco movido para dentro da
   camada) e ficou vermelho apontando os três seletores, antes de merecer
   confiança.

**Decisão registrada:** corrigir no tema em vez de desligar a opção do WPBakery.
Desligar resolveria neste site, mas é configuração global do construtor — um tema
revendável não pode depender de um ajuste que qualquer administrador religa sem
saber. As duas coisas convivem: se a opção for desligada, o seletor do tema
deixa de casar e o grid nativo assume.

Duas precisões que evitam alarme falso no futuro:

- **só a home está afetada.** As outras 5 páginas com WPBakery usam colunas de
  largura total, e as `vc_col-sm-3` de "Fotos e Vídeos" medem 0px (colunas
  vazias);
- **a imagem que parecia quebrada no print não estava quebrada** — 0 imagens com
  `naturalWidth === 0`. Era carregamento em andamento.

No mesmo dia, o **breadcrumb foi redesenhado** (recuo alinhado ao conteúdo,
chevron em geometria CSS pura, item atual com peso maior, alvo de toque de 16px
para 26,8px). Detalhe que só apareceu por medir: o recuo teve de ser escopado a
`main.content-container > .arena-breadcrumb`, porque em cinco templates a trilha
é renderizada **dentro** de `.content-column`, que já dá os 15px — uma regra
solta produziria 30px e desalinharia essas páginas.

### 30/07 — site fora do ar por 20 minutos (loop de redirect)

**O que aconteceu.** Depois da auditoria de PageSpeed, o dono do site criou no
painel da Hostinger a regra que meu relatório sugeria:

```
powerarena.example.com/  →  https://www.powerarena.example.com
```

O site inteiro passou a responder **301 em loop infinito**, em todos os caminhos.

**Por quê.** No DNS, `www.powerarena.example.com` é **alias** de
`powerarena.example.com`. A regra, portanto, também casava com o próprio `www`:
`www` → `www` → `www`, para sempre. E como ela age na camada `hws` (servidor da
Hostinger), pegava **todo caminho** — inclusive `/robots.txt` e arquivos de CSS,
que nunca chegariam ao WordPress.

**Culpa é minha.** O achado estava certo; a recomendação estava vaga. Eu escrevi
*"corrigir lá, para apontar à raiz"* quando o certo era **"apague a regra"** — a
canonicalização sem-`www` → `www` **já funcionava sem ela** (medido na própria
auditoria: `https://powerarena.example.com/` → 301 → `https://www.powerarena.example.com/`).
Uma regra de domínio cujo destino é o mesmo domínio sempre vira loop, e eu não
avisei isso.

**Três tentativas de conserto que falharam** — e o motivo de terem falhado é a
lição:

1. restaurar o `.htaccess` do backup → sem efeito;
2. restaurar as opções do LiteSpeed aos valores originais → sem efeito;
3. procurar cache de borda (query-busting, `Cache-Control: no-cache`) → sem efeito.

Todas partiam da suposição de que **eu** havia causado o problema, porque a
mudança de opções do LiteSpeed foi imediatamente anterior. A suposição estava
errada, e insistir nela custou tempo.

**O teste que resolveu o diagnóstico** foi parar de tentar consertar e medir:

| Evidência | Conclusão |
|---|---|
| `/robots.txt` e `style.css` também davam 301 | arquivo estático não passa por PHP/WordPress |
| resposta com `server: hws` + `platform: hostinger` e **zero** header `X-LiteSpeed-*` | a requisição morria antes da camada de cache |
| loop reproduzido **de dentro do servidor** (`https://127.0.0.1` com Host header) | não era cache de borda/CDN |
| `.htaccess` lido de ponta a ponta: nenhuma regra para a raiz | não era reescrita de `.htaccess` |

Ou seja: acima do WordPress, na plataforma. Nada dentro do site podia causar nem
resolver.

**Resolução.** O dono apagou a regra. Verificado depois: 200 em todos os
caminhos, `X-Litespeed-Cache: HIT` de volta, e `admin-ajax.php` respondendo
**400** em vez de 301 — o que devolveu os comentários (wpDiscuz) e os anúncios,
que aquela regra estava quebrando.

**Duas coisas ficaram pendentes**, registradas em
[13 — Pendências](13-pendencias.md):

- os itens 1 e 2 da auditoria (Guest Mode e Separate Mobile Cache) foram
  **aplicados, revertidos e nunca medidos**. Se forem tentados de novo, o caminho
  é a interface do plugin, com alguém olhando o site;
- `http://powerarena.example.com` **continua** indo para `/wp-admin/`. Não é a regra
  apagada (testado com query-busting: não é cache), não está no `.htaccess` e não
  está nas duas regras visíveis do painel.

**Também vale registrar o que NÃO é problema:** o bloco
`### marker ASYNC ###` do `.htaccess`, que menciona
`/wp-admin/admin-ajax.php`, **não é um redirecionamento** — ele só define
`noabort` para o LiteSpeed não matar as próprias requisições assíncronas. É do
plugin, é necessário, e não tem relação com o incidente.

---

## Erro 1 — desenvolvi na versão errada

O `.wp-env.json` estava em **WordPress 6.4 / PHP 8.2**, enquanto a produção roda
**7.0.2 / PHP 8.5.8**. A especificação inicial estava certa; a configuração é que
não tinha sido alinhada.

Pior: `"core"` apontava para `WordPress/WordPress#6.4` — um **branch**, não uma
tag. Um `wp-env start` posterior baixou um snapshot diferente do mesmo branch, e
o `CompatibilityTest` **começou a falhar sem que uma linha de código mudasse**.

O diagnóstico foi longo porque o PHPUnit, nesse ambiente, imprimia
`syntax error, unexpected identifier "SERIALIZATION_FORMAT_USE_UNSER..."` em vez
da mensagem real (bug de renderização do `doctrine/instantiator`). A falha real
estava numa fase **posterior ao corpo do teste** (`assertPostConditions`): o teste
refazia `do_action('init')` e afirmava que o core emitiria certos avisos de
`_doing_it_wrong` — e aquele snapshot de core não emitia mais.

**Provado empiricamente**, não inferido: o código do commit antigo, byte a byte,
falhava igual no ambiente novo (`git worktree` + container com o mount trocado).

**Lições aplicadas:**

- versões de desenvolvimento **fixas e iguais à produção**;
- `core` aponta para **tag**, nunca branch;
- o teste passou a chamar `Compatibility::trimCoreBloat()` diretamente, em vez de
  refazer `init` e afirmar ruído do core. Sobreviveu depois a um salto de
  6.4 → 7.0.2 sem mudança;
- e a verificação que dá confiança na correção: **quebrar de propósito** (mudar a
  prioridade de `remove_action` de 7 para 99) e confirmar que o teste fica
  vermelho.

---

## Erro 2 — amarrei o breadcrumb a um plugin específico

Sete templates chamavam `yoast_breadcrumb()` **direto**. O site trocou para o
Rank Math e a trilha **sumiu de todas as páginas** — sem erro, sem log, sem
pista. Cada template tinha `function_exists()` na frente e simplesmente pulava.

Duas coisas erradas de uma vez: acoplamento a um fornecedor **e** falha
silenciosa. Corrigido com `Arena\Seo` (Rank Math → Yoast → SEOPress → nada) e um
teste que falha se um template voltar a chamar o plugin diretamente. Detalhes em
[08 — Compatibilidade](08-compatibilidade-plugins.md#rank-math--e-a-armadilha-do-breadcrumb).

---

## Erro 3 — auditoria com ponto cego

A primeira rodada de acessibilidade rodou **só em mobile, numa matéria**. Nessa
combinação a barra de navegação desktop está `display:none` e não existe
paginação. Resultado: o Lighthouse deu **100**, e **duas superfícies com
contraste insuficiente** (barra de navegação e número da página atual, ambas
4,02:1) passaram batido — porque a auditoria não podia vê-las.

Foram encontradas depois, numa **revisão de código**. A auditoria não estava
errada; a **cobertura** estava.

**Lição aplicada:** as execuções passaram a ser deliberadamente complementares —
mobile + matéria **e** desktop + categoria paginada. Um "100" só vale para o que
a execução conseguiu enxergar.

Do mesmo tipo, no mesmo período: um relatório afirmou "0 overflows" **sem ter
rodado a verificação** (tinha se conectado ao alvo do *browser* em vez do alvo da
*página* no CDP). Rejeitado e refeito com medição real.

---

## Erro 4 — painel de opções que nunca apareceu

O painel foi feito com `acf_add_options_page()`. Isso é recurso do **ACF PRO**, e
o site tem a versão gratuita — então `OptionsPanel::boot()` saía na primeira
linha e **nenhum menu era criado**. O dono do site ficou sem encontrar as
configurações ("não achei opções de estilização do tema"), com razão.

Falha de projeto, não de código: **a única tela de configuração do tema dependia
de um recurso pago, sem ninguém ter verificado qual versão estava instalada.**
Depois descobriu-se que o ACF **não está instalado** em produção — o que tornava
o problema total, não parcial: nem o logotipo podia ser definido.

Correção em duas etapas: primeiro o **Customizer nativo**
(`add_theme_support('custom-logo')` + seção própria), depois o **painel "Arena"**
com abas, sem plugin nenhum. Fonte única da verdade em `Arena\Settings`, as duas
telas gravando no **mesmo `theme_mod`** — sem sincronização possível de dar
errado. Ver [07 — Opções e painel](07-opcoes-e-painel.md).

Dois detalhes que só os testes pegaram:

- **chaves numéricas de array em PHP viram inteiro** (`'800' => 800`), então a
  validação estrita dos selects descartava todo valor numérico;
- o campo de cor passou a aceitar hex **sem** `#`, porque é o que se digita
  naturalmente.

---

## Erro 5 — corrigir para o lado errado (quase)

A revisão apontou que a home repetiria posts: o hero (5 mais recentes) e
"Últimas notícias" (8 mais recentes) mostrariam os mesmos itens.

Antes de "corrigir", **o site de referência foi conferido**: o hero real e o
grid real têm **3 posts em comum** — porque aquele bloco carrega
`disable_duplicate="0"`. **A repetição era fiel ao original.**

O defeito verdadeiro era outro e mais discreto: o atributo `disable_duplicate`
**não estava implementado**, então os blocos que *pediam* deduplicação também
repetiam. Implementado no `Renderer` (`static $shown` + `post__not_in`).

**Lição:** "o revisor apontou" não é o mesmo que "está errado". Verificar contra
a referência evitou trocar uma paridade correta por uma divergência.

Do mesmo tipo: houve uma instrução minha, errada, para **colorir os badges por
categoria**. A referência **não** tem cor de badge por termo — as cores por seção
pertencem só às bandeiras de título. Revertido.

---

## Erro 6 — especificidade quando o problema era a cascata

As regras de alinhamento de mídia (`aligncenter` etc.) não funcionavam. Medido no
site: a caixa **encolhia** para a largura da imagem, mas as duas margens
computavam `0px`.

A causa não era especificidade: o core emite `:where(figure){margin:0 0 1em}`
**sem camada**, e uma declaração **sem camada vence qualquer declaração dentro de
`@layer`** na mesma origem, por mais específica que seja. Nenhuma quantidade de
especificidade resolveria. A correção foi **tirar as regras do `@layer`**.

É a mesma raiz estrutural dos 3 `!important` que sobrevivem no CSS (todos contra
o `js_composer`). Há um **teste-guarda** que percorre a folha e falha se um
seletor de alinhamento voltar para dentro de uma camada — e ele foi verificado
nos dois estados (vermelho no arranjo com bug, verde no corrigido) antes de
merecer confiança.

---

## Erro 7 — um local de menu que era isca

Existia um local de menu `arena_primary` chamado **"Menu Principal"**,
registrado mas **nunca renderizado** por nenhum template — sobra de antes de
conhecermos os slugs do Publisher. No Customizer ele tinha o nome mais óbvio,
então atribuir o menu do cabeçalho ali **não fazia nada**.

Removido; os locais restantes foram renomeados para dizer **onde cada um
aparece**. O teste novo afirma o invariante real: **todo local registrado tem de
ser renderizado por algum template.**

---

## Diagnósticos errados que não viraram código

Registrados porque cada um consumiu tempo:

| Suspeita | O que era de fato |
|---|---|
| "a matéria renderiza vazia" | eu tinha aberto um **rascunho** (`?p=`), que dá 404; e o `404.php` ainda não existia, então caía num `index.php` quase vazio |
| "overflow horizontal a 420px" | **artefato de captura**: aquele build do Chrome limita `--window-size` abaixo de ~500px. Medido corretamente via `Emulation.setDeviceMetricsOverride`: `scrollWidth == clientWidth` a 360/420/768 |
| "um tile do hero não carrega — lazy-load ou arquivo corrompido" | o arquivo era válido (HTTP 200, decodifica, renderiza isolado). Era o `sizes="auto,…"` do WP 7 com a imagem dentro de um `position:absolute` |
| "o wpDiscuz declara `wp-edit-post` direto" | estava a **dois níveis** na árvore de dependências; checar só as diretas não achava nada |
| "a faixa branca antes do rodapé é do bloco" | era o `<main>` com fundo claro e *padding* inferior contra uma linha escura de ponta a ponta |

E um resíduo conhecido, documentado em vez de escondido: a matéria tem
**6px de `scrollWidth`** extra vindos do painel off-canvas (fora da tela por
`translateX`, contido por `overflow-x:hidden`). Não é visível para o usuário.
