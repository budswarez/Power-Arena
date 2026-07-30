# 13 — Pendências

O que está aberto, com contexto suficiente para retomar sem reconstruir o
raciocínio. Cada item diz **o que é**, **por que ficou de fora** e **como
verificar/retomar**.

Ordem: primeiro o que tem risco ou prazo, depois o que é melhoria.

---

## Ação manual no site: widgets da sidebar

**Situação.** O Publisher guardava os widgets da sidebar numa área dele
(`primary-sidebar`). O Arena registra a sua (`arena-primary`). **A troca de tema
não migra widgets** — é comportamento padrão do WordPress: widgets de uma área
que sai do tema ativo ficam "inativos", preservados, mas não aparecem em lugar
nenhum.

**Consequência se ninguém agir:** a coluna lateral fica vazia em matérias,
páginas e categorias.

**Como resolver.** *Aparência → Widgets* → arrastar os widgets da seção
**"Widgets Inativos"** para **Sidebar Principal** (`arena-primary`). Se a seção
não aparecer, recriar manualmente.

**Por que o tema não faz isso sozinho:** migrar widgets é decisão do dono do
site, não adivinhação do tema. Está registrado como intencional no
[DEPLOY.md](../DEPLOY.md).

> **Atenção:** essa tela tem dois contornos ativos por causa da hospedagem e do
> wpDiscuz. Se ela der erro ao salvar, leia
> [09 — Infraestrutura](09-infraestrutura-producao.md#a-rota-batchv1-é-removida-pela-plataforma)
> antes de suspeitar do tema.

---

## Cache mobile nunca é servido (`BYPASS`)

Dos dois ajustes de LiteSpeed que a auditoria de 30/07
([10 — Medições](10-medicoes-e-performance.md#auditoria-de-pagespeed--30072026))
apontou, **o primeiro foi feito** e este é o que resta:

| Item | Opção | Situação |
|---|---|---|
| Guest Mode carregava a página duas vezes | `guest`, `guest_optm` | ✅ desligado — score 70→78, LCP 6,5→5,1 s |
| **Cache de página nunca servido no mobile** | `cache-mobile` | **pendente** |

**Sintoma medido, ainda hoje:** requisições com UA de celular respondem
`X-Litespeed-Cache: BYPASS`/`MISS` e pagam um render PHP completo (~500–700 ms);
com UA de desktop, `HIT` em ~130 ms.

**Por que desligar o cache mobile separado é seguro:** o Arena é responsivo e
entrega a MESMA marcação para os dois. Comparei o HTML servido a cada UA — 94,6%
idêntico, e as diferenças eram todas do próprio LiteSpeed (lazy-load,
`litespeed_ui_events`), consequência de uma cópia estar cacheada e a outra não.
Nada vinha do tema.

**Como fazer, com segurança** (o protocolo que funcionou no item 1):

1. registre o valor atual e faça backup do `.htaccess` — o LiteSpeed pode
   reescrevê-lo ao salvar (no item 1 não reescreveu; ao mexer em `cache-mobile`,
   reescreve: é ele que gerencia o marcador `### marker MOBILE ###`);
2. mude **só** `cache-mobile`;
3. **verifique a saúde do site antes de medir ganho** — caminhos principais,
   variantes de domínio, 404 e `admin-ajax`;
4. só então meça:

```bash
curl -sD - -o /dev/null -A "Mozilla/5.0 (Linux; Android 13) Mobile" \
  https://www.pichauarena.com.br/ | grep -i x-litespeed-cache
```

Hoje: `BYPASS`/`MISS`. Se passar a `HIT`, funcionou.

> **Atenção ao `.htaccess`:** mexer em `cache-mobile` faz o LiteSpeed reescrever o
> arquivo. O bloco `# BEGIN Arena canonical host` fica **fora** dos marcadores
> dele e sobrevive a isso — mas confira depois
> (`grep -c 'BEGIN Arena canonical host' .htaccess` deve devolver `1`), porque
> perdê-lo faz o problema do `/wp-admin/` voltar.

**Estado atual:** `guest=0`, `guest_optm=0`, `cache-mobile=1`.

---

## `http://` sem `www` ia para `/wp-admin/` — RESOLVIDO em 30/07

`http://pichauarena.com.br/qualquer-coisa` respondia 301 para
`https://www.pichauarena.com.br/wp-admin/`, que caía no `wp-login.php`.

**Quem emitia:** o **WordPress**, não a plataforma. A resposta trazia
`X-Redirect-By: WordPress` e `X-Processing-Time: 0.302` — ou seja, a requisição
chegava ao PHP, rodava o WordPress inteiro e devolvia o destino errado. Arquivos
estáticos existentes não eram afetados (servidos 200 em `http`), o que confirma
que só o que passava pelo WordPress era redirecionado.

**Oito candidatos descartados com evidência:** regras do painel (só resta a do
`/evento`, e o problema era anterior à regra apagada), `.htaccess`,
`WP_HOME`/`WP_SITEURL` (não definidos), `home`/`siteurl` no banco (corretos),
redirects do Rank Math, `wp_redirect_admin_locations()` do core (só age em 404 —
mas uma matéria válida também era redirecionada), o mu-plugin
`hostinger-preview-domain.php` (só ativa com o header `X-Preview-Indicator`) e
cache (`MISS`, e reproduzia com query-busting).

> **A linha exata nunca foi identificada.** Achá-la exigiria instrumentar uma
> requisição web real, ou seja, código temporário em produção. Optamos por
> resolver **antes do PHP**, o que torna a causa irrelevante.

**A correção** — bloco no topo do `.htaccess`, fora de qualquer marcador de
plugin:

```apache
# BEGIN Arena canonical host
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{HTTP_HOST} !^www\. [NC]
RewriteCond %{REQUEST_URI} !^/\.well-known/ [NC]
RewriteRule ^ https://www.pichauarena.com.br%{REQUEST_URI} [R=301,L]
</IfModule>
# END Arena canonical host
```

**Por que não pode virar loop:** a condição exige que o host **não** comece com
`www.`, e o destino **sempre** tem `www.` — a requisição redirecionada nunca volta
a casar. Foi exatamente essa exclusão que faltou na regra do painel que derrubou o
site (ela apontava o domínio para um destino que ela mesma casava).

`.well-known` fica de fora de propósito: é o caminho de validação/renovação de
certificado.

**Medido depois:**

| | Antes | Depois |
|---|---|---|
| `http://pichauarena.com.br/` | → `/wp-admin/` → `wp-login.php` | → `https://www.pichauarena.com.br/` (**1 redirect**) |
| `http://pichauarena.com.br/csgo/cs2/` | → `/wp-admin/` | → a própria matéria |
| `https://pichauarena.com.br/` | correto, mas via PHP | correto, no servidor |
| custo de PHP no redirect | `X-Processing-Time: 0.302` | **`0.000`** |
| `/evento` (as duas variantes) | → `evento.pichauarena.com.br` | **preservado** |
| `.well-known` | 404, sem redirect | **404, sem redirect** |

> ### ⚠️ Se o `.htaccess` for regenerado, este bloco se perde
>
> Ele fica **fora** dos marcadores gerenciados por plugin (`LSCACHE`,
> `WordPress`, `EWWWIO`), então reescritas normais do LiteSpeed e do WordPress o
> preservam. Mas um "resetar `.htaccess`" pelo painel da Hostinger apagaria tudo.
> Se o problema do `/wp-admin/` reaparecer, **é isto que sumiu.**

**Ainda em aberto (opcional):** o site não envia `Strict-Transport-Security`
(HSTS). Com HSTS o navegador nunca mais faria a requisição em `http` depois da
primeira visita, eliminando a classe inteira do problema — mas é um compromisso
duradouro com HTTPS, então é decisão separada.

---

## Redirecionamentos do Yoast Premium

**Situação.** Existem **743 regras** de redirecionamento guardadas na option
`wpseo-premium-redirects-base`. O Yoast saiu; o Rank Math tem módulo próprio de
redirecionamentos, mas **as regras não foram migradas**.

**Por que não é simplesmente "migrar as 743".** Boa parte delas o WordPress
resolve sozinho, via `_wp_old_slug` — o post mudou de slug e o core redireciona
o antigo. Migrar tudo às cegas criaria centenas de regras redundantes e algumas
**conflitantes** (regra apontando para um destino diferente do que o core já
faz).

**O que existe pronto.** Um script de auditoria **somente leitura**
(`auditar-redirects.php`) que testa cada origem com `wp_remote_head`
(`redirection => 0`) e classifica em quatro grupos:

| Grupo | Significado |
|---|---|
| `ok` | já redireciona (3xx) — o WordPress resolve |
| `destino_ruim` | redireciona, mas **para destino diferente** do que o Yoast definia |
| `quebradas` | 404 ou 200 na URL antiga — **só estas precisam migrar** |
| `erro` | falha de rede no teste |

Grava o resultado em `/tmp/arena-redirects-auditoria.json` e imprime um resumo
com amostra das quebradas.

**Como retomar.**

1. rodar o script pelo WP-CLI em produção (é leitura, mas gera 743 requisições —
   rodar fora do horário de pico);
2. **cadastrar no Rank Math apenas o grupo `quebradas`**;
3. revisar caso a caso o grupo `destino_ruim` — decidir se o destino do core ou o
   do Yoast é o correto.

**Risco de não fazer:** links antigos indexados que hoje dão 404 continuam dando
404 — perda de tráfego de busca, silenciosa.

---

## `llms.txt` de 19 MB

**Situação.** O módulo `llms.txt` do Rank Math gera um arquivo público que,
medido em produção, tinha **18.954.278 bytes (~18,1 MiB)**: praticamente todos os
posts do site, com título, URL e descrição, num único arquivo texto.

**Impacto.** Não afeta visitantes nem o tema. É peso servido a qualquer
rastreador que peça o arquivo, e é regenerado pelo plugin.

**Decisão a tomar** (é do dono do site, não do tema): manter, limitar o escopo do
módulo (só páginas, ou só os N posts mais recentes), ou desligar o módulo. Não há
nada a fazer no tema em nenhum dos casos.

---

## Instrumentação temporária de memória — RESOLVIDO em 30/07

Para medir o pico de memória por requisição, um bloco temporário gravava uma
linha por requisição em `/tmp/arena-memoria.log` no `shutdown`.

**Fechado no deploy da 0.2.0:** o `functions.php` do tema filho em produção não
contém mais o bloco (`grep arena-memoria` = 0 ocorrências), e o
`/tmp/arena-memoria.log` que havia ficado no servidor (134 bytes, de 26/07) foi
removido.

O método de medição continua documentado em
[09 — Infraestrutura](09-infraestrutura-producao.md#memória-e-custo-por-plugin)
para quando for preciso medir de novo — e a lição é reinstrumentar de propósito,
não deixar ligado.

---

## Duas cópias do tema filho

**Situação.** Existem duas pastas `arena-child` na máquina de desenvolvimento:

| Caminho | Papel |
|---|---|
| `themes/arena/arena-child/` | **canônica** — é o que o git versiona e o que o `.wp-env.json` monta (`"./arena-child"`) |
| `themes/arena-child/` | **legada** — tem `.git` próprio, de quando o filho era repositório separado |

**Por que importa.** A cópia legada fica para trás e **já causou um erro real**:
no primeiro empacotamento da 0.2.0 o script leu a irmã legada, e o pacote saiu
com o pai em 0.2.0 e o filho em 0.1.0 — o site não quebra, mas o painel mostra
versões divergentes e o cache-busting do filho não gira.

**Já corrigido:** o `bin/package.sh` passou a preferir explicitamente a cópia de
dentro, avisa se cair na legada, e **aborta** se as versões de pai e filho
divergirem (o mesmo portão vale para `ARENA_VERSION`).

**O que falta decidir** (é sua escolha, não do tema): apagar
`themes/arena-child/`. Ela tem histórico git próprio, então merece um olhar antes
— se não houver nada lá que não esteja no repositório do Arena, remover elimina a
armadilha na origem.

---

## Contornos que devem ser removidos quando a causa sumir

Dois trechos de `Arena\Compatibility` existem por defeitos de terceiros. Ambos
foram escritos para **se desfazerem ou serem removidos com segurança**, mas
precisam de alguém para conferir de vez em quando:

| Contorno | Sai quando | Como saber |
|---|---|---|
| tela clássica de widgets | a Hostinger parar de remover `/batch/v1` | ele já **se desliga sozinho** — a condição é a ausência da rota. Só o código fica sobrando |
| `keepPostEditorOutOfWidgetScreens` | o wpDiscuz corrigir a dependência `wp-edit-post` | testar a tela de widgets **sem** o método e ver se salva |

Contexto completo em [08](08-compatibilidade-plugins.md) e
[09](09-infraestrutura-producao.md).

---

## `tabs="cat_filter"` — deliberadamente fora de escopo

**O que é.** Na grade "Últimas notícias" da home, o Publisher renderiza uma faixa
de abas de categoria acima da grade quando esse atributo está presente,
refiltrando o mesmo bloco no cliente sem recarregar a página.

**Estado atual.** `shortcode_atts()` descarta o atributo silenciosamente — a
grade renderiza como hoje (todos os posts, sem faixa de abas), igual ao que
aconteceria com um atributo digitado errado. **Sem regressão.**

**Por que ficou fora.** Não é uma lacuna de marcação estática como `hide_title`
ou a ordenação de `post_ids` (poucas linhas cada, todas implementadas). Um
`cat_filter` de verdade precisa de: re-consulta no cliente (AJAX por aba, ou
pré-renderizar todas as abas e alternar visibilidade), um endpoint novo
(REST/admin-ajax) **ou** um payload inicial bem maior, marcação nova para a faixa
de abas, e JS próprio **com acessibilidade** (abas exigem `tabindex` móvel e
`aria-selected`, não só um `click`). É trabalho de tamanho "funcionalidade", que
não cabia numa rodada de acabamento.

A decisão está registrada no próprio código, em `inc/Blocks/Shortcodes.php`.

---

## Verificações que só a produção fecha

| Item | Por que não fecha localmente |
|---|---|
| **Comentários (wpDiscuz)** | o plugin **não inicializa** no sandbox ativado por CLI (nenhum filtro `comments_template`, nenhum asset). O lado do tema é a chamada padrão `comments_template()`, que está correta — mas a renderização real precisa ser vista no site |
| **Anúncios (Ad Inserter)** | dependem de configuração e do conteúdo real |
| **Lighthouse "Boas práticas"** | os 77 pontos são limitados por `is-on-https` e `inspector-issues`, artefatos de `localhost`. Uma execução no site em HTTPS deve subir esse número sem nenhuma mudança de código |

Checklist de validação pronto no [DEPLOY.md](../DEPLOY.md#checklist-de-validação-no-preview).

---

## Decisões pendentes do dono do site

Nada aqui é bloqueio técnico — são escolhas:

- **Publisher.** Continua instalado (desativado) para permitir rollback imediato.
  Quando a validação estiver concluída e a confiança firmada, decidir se remove.
  Enquanto estiver lá, o rollback é uma ativação de tema.
- **5 plugins desativados, não removidos** na limpeza: `health-check`,
  `wordpress-importer`, `mammoth-docx-converter`, `rvg-optimize-database`,
  `wp-file-manager`. Desativado já elimina o custo de execução; remover elimina o
  código do disco. `wp-file-manager`, em particular, é o tipo de plugin que vale
  remover em vez de deixar desativado.
- **Preview por token.** O mu-plugin **não está instalado**, por escolha — com o
  Arena já ativo, ele faria todo administrador logado ver o outro tema. Ver
  [14 — Runbook](14-runbook-operacional.md#preview-por-token-quando-usar-e-quando-não).

---

## Resíduo conhecido, documentado e aceito

- **6px de `scrollWidth`** extra na matéria, vindos do painel off-canvas (fora da
  tela por `translateX`, contido por `overflow-x:hidden`). Não é visível nem
  rolável para o usuário. Registrado para que ninguém "descubra" isso de novo e
  saia atrás de um bug de layout.
- **Mosaico de destaque da categoria** não é *full-bleed* como na referência; o
  padrão de proporções (1 grande + 1 médio + 2 pequenos) foi implementado, a
  sangria de ponta a ponta não.
