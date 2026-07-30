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

## Instrumentação temporária de memória

**Situação.** Para medir o pico de memória por requisição, um bloco temporário
foi adicionado ao `functions.php` do tema filho: ele grava uma linha por
requisição em `/tmp/arena-memoria.log` no `shutdown`.

**Verificado agora:** o `arena-child/functions.php` **desta cópia local está
limpo** — só o enfileiramento do estilo.

**O que falta:** confirmar que a cópia **em produção** também está limpa. Se o
bloco tiver ficado lá, ele escreve num arquivo de log a cada requisição — não
quebra nada, mas é I/O desnecessário e um arquivo crescendo em `/tmp`.

**Como verificar:** procurar `arena-memoria` no
`wp-content/themes/arena-child/functions.php` do servidor. Se aparecer, remover o
bloco (ele é autocontido, entre os comentários "MEDIÇÃO TEMPORÁRIA") e limpar o
`/tmp/arena-memoria.log`. Método completo em
[09 — Infraestrutura](09-infraestrutura-producao.md#memória-e-custo-por-plugin).

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
