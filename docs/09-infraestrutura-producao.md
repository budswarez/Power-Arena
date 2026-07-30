# 09 — Infraestrutura de produção

O `pichauarena.com.br` roda numa **hospedagem gerenciada da Hostinger**, numa
stack que a própria empresa identifica como **`h5g`**. Ela faz coisas que uma
instalação comum de WordPress não faz, e algumas delas **você não pode
desfazer**.

> **Leia este documento antes de investigar qualquer coisa que "deveria
> funcionar e não funciona" no servidor.** Cada seção aqui custou horas de
> diagnóstico, quase sempre com a suspeita inicial recaindo sobre o tema.

---

## O ambiente

| Item | Valor |
|---|---|
| WordPress | 7.0.2 |
| PHP | 8.5.8 |
| Servidor web | LiteSpeed |
| Banco | MariaDB |
| WP-CLI | disponível em `/usr/local/bin/wp` |
| Acesso | SSH **somente por senha** (sem chave pública) |

O ambiente local (`wp-env`) está fixado nas **mesmas versões**, de propósito —
WordPress `#7.0.2` (tag, não branch) e PHP `8.5`. Ver
[03 — Ambiente local](03-ambiente-local.md).

---

## O que é gravável — e o que não é

Esta é a restrição que mais afeta o trabalho do dia a dia:

| Caminho | Situação |
|---|---|
| `public_html/` e abaixo | **gravável** |
| diretório *home* do usuário | **não gravável** |
| `wp-content/mu-plugins/` | **symlink** para a árvore gerenciada da plataforma |
| `/opt/h5g/usr/mu-plugins/` | `root:root` — leitura apenas |

Consequências práticas:

- **Não dá para subir um arquivo para fora do `public_html`.** O empacotamento
  do tema grava o `.zip` em `renew/entrega-arena/` justamente para o
  distribuível não ficar publicamente baixável dentro do `public_html`
  (commit `aaa40f1`) — mas isso vale para a *máquina de desenvolvimento*. No
  servidor, qualquer arquivo temporário de instalação fica dentro da raiz web e
  **precisa ser apagado depois**.
- Durante a instalação do tema, o `.zip` foi enviado para um diretório oculto
  dentro de `themes/`, extraído, e **o diretório e o zip foram apagados** — não
  se deixa um arquivo distribuível num caminho servido pela web.

### `mu-plugins` não é seu

`wp-content/mu-plugins` é um **symlink** para a árvore gerenciada
(`__wp__`) da Hostinger. Isso significa que:

- **você não pode instalar um mu-plugin próprio** de forma confiável;
- contornos que "deveriam" morar num mu-plugin acabam tendo que morar no
  **tema**, que é o único lugar gravável na cadeia de carregamento. É por isso
  que `Arena\Compatibility` existe e carrega correções que conceitualmente não
  são de tema — ver [08 — Compatibilidade](08-compatibilidade-plugins.md);
- o `mu-plugins/arena-preview.php` que acompanha o tema está **parado dentro de
  `themes/arena/mu-plugins/`**, onde é **inerte** (mu-plugins só carregam
  automaticamente a partir de `wp-content/mu-plugins`). Ele não foi instalado —
  ver [14 — Runbook](14-runbook-operacional.md#preview-por-token-quando-usar-e-quando-não).

---

## A rota `/batch/v1` é removida pela plataforma

**Sintoma.** Em `Aparência → Widgets`, ao salvar: *"Ocorreu um erro. Cannot read
properties of undefined (reading '0')"*. Nada é gravado — a requisição de
gravação **nem sai do navegador**.

**Causa medida.** O mu-plugin da plataforma, em
`/opt/h5g/usr/mu-plugins/hostinger-h5g-plugin.php`, faz

```php
unset($endpoints['/batch/v1']);
```

no filtro `rest_endpoints` com prioridade `PHP_INT_MAX`, com o comentário
*"Temporary block /batch/v1 for security reasons"*. O arquivo é `root:root` — o
dono do site **não pode alterá-lo**.

Por que isso quebra o editor: o editor de widgets em blocos salva por essa rota.
Antes de enviar os dados ele faz `OPTIONS /batch/v1` e lê
`resposta.endpoints[0].args.requests.maxItems`
(`wp-includes/js/dist/core-data.js`, `defaultProcessor`). Sem a rota
registrada, `rest_handle_options_request()` responde **200 com corpo `[]`**,
`endpoints` fica indefinido, e o JS quebra em `undefined[0]`.

**Confirmação de que é da plataforma:** o mesmo acontece em **outro site da
mesma conta, com outro tema**.

**O contorno** (`Compatibility::classicWidgetsScreenWhenBatchRouteMissing()`)
volta a tela de widgets para a interface clássica — que salva por POST de
formulário, sem tocar na REST API. Escopo estreito de propósito:

- **só em `widgets.php`.** O painel de widgets do Customizer usa outro editor
  (`wp-customize-widgets`), que **não** passa por `/batch/v1` e funciona
  normalmente. Desligá-lo ali só tiraria a interface melhor de quem já consegue
  usá-la;
- **só quando a rota está realmente ausente.** No dia em que a hospedagem
  remover o bloqueio, o editor em blocos volta sozinho, sem ninguém precisar
  lembrar de desfazer nada. `get_routes()` reaplica o filtro `rest_endpoints` a
  cada chamada, então a verificação reflete o estado real.

> Há um **segundo** motivo para essa mesma tela quebrar, vindo do wpDiscuz.
> Os dois contornos são independentes e coexistem. Ver
> [08 — Compatibilidade](08-compatibilidade-plugins.md#wpdiscuz--e-a-tela-de-widgets-que-não-salvava).

---

## Ruído de PHP 8.5 que não é seu

Em PHP 8.5 aparecem avisos de *deprecation* que **não vêm do tema**. Saber disso
economiza uma caçada:

- **Yoast**, `meta-tags-context-memoizer`: 6 avisos no HTML. Só aparecem porque
  `WP_DEBUG_DISPLAY` está ligado no ambiente local; em produção vão para o log.
  **Zero avisos vêm de `themes/arena`** — isso foi verificado explicitamente.
- **WP-CLI**, `php-cli-tools` (`Colors.php`): `Deprecated: Using null as an
  array offset`, disparado durante `wp theme activate`. É a biblioteca embutida
  no próprio WP-CLI.

Verificação usada: `php -l` em todos os PHP do tema sob PHP 8.5 — limpo.

---

## Memória e custo por plugin

O tema não é o que pesa nessa instalação, mas isso teve de ser **medido**, não
argumentado.

**Método 1 — pico por requisição.** Um trecho temporário no `functions.php` do
tema filho registra, no `shutdown` (prioridade `PHP_INT_MAX`, só observando),
uma linha por requisição em `/tmp/arena-memoria.log`:

```
HH:MM:SS | front home     | pico  101.0 MB | final   XX.X MB | queries  NNN | /
```

Registra pico, uso final, número de queries, tipo (`front`/`admin`/`rest`/`cron`)
e contexto (`home`/`post`/`arquivo`/`busca`/`404`). O pico medido nas
requisições de front-end ficou na ordem de **101 MB** — praticamente todo ele
anterior ao tema.

> **Isso é instrumentação temporária.** Se você encontrar esse bloco no
> `arena-child/functions.php`, ele deveria ter sido removido. Ver
> [13 — Pendências](13-pendencias.md).

**Método 2 — custo de cada plugin, por exclusão.** `custo-por-plugin.sh` carrega
o WordPress com todos os plugins ativos, mede
`memory_get_peak_usage(true)`, e repete pulando **um plugin por vez**
(`wp --skip-plugins=<nome>`), imprimindo a diferença em MB. É somente leitura e
roda pelo WP-CLI com `memory_limit=512M`.

---

## Higiene do banco

Uma limpeza autorizada foi executada em produção, **com backup de cada item
antes** e verificação de integridade dos `.gz` (`gzip -t` + contagem de
`INSERT INTO`) **antes** de qualquer remoção. O que saiu:

| Item | Por quê |
|---|---|
| `wp_postmeta_bkp_20260724` (**287 MB**) | tabela de backup esquecida por outra ferramenta |
| 8 tabelas `wp_yoast_*` | plugin inativo — **as metas `_yoast_wpseo_*` foram preservadas** |
| `wp_check_email_log`, `wp_check_email_spam_analyzer` | plugin desinstalado |
| `postmeta` de Imagify, Smush, Trinity Audio, WP Fastest Cache | plugins que já saíram |
| `oembed_cache` (posts + meta) | cache regenerável |
| transients | cache regenerável |
| `wp-content/object-cache.php` | drop-in órfão, sem backend de cache de objetos |
| 5 plugins fora de produção | `health-check`, `wordpress-importer`, `mammoth-docx-converter`, `rvg-optimize-database`, `wp-file-manager` — **desativados**, não removidos |

O EWWW foi **deliberadamente não tocado**.

Os scripts (`fazer-backups.sh`, `executar-limpeza.sh`) estão descritos em
[14 — Runbook](14-runbook-operacional.md#limpeza-de-banco-como-foi-feita).

### O `llms.txt` de 19 MB

O módulo `llms.txt` do Rank Math gera um arquivo que, medido em produção, tinha
**18.954.278 bytes (~18,1 MiB)** — uma listagem de praticamente todos os posts
do site, com título, URL e descrição, num único arquivo texto servido
publicamente.

Não é bug do tema e não afeta visitantes, mas é peso servido a qualquer
rastreador que o peça, regenerado pelo plugin. Está registrado em
[13 — Pendências](13-pendencias.md#llmstxt-de-19-mb).

---

## Acesso ao servidor

- **SSH é somente por senha.** Não há chave pública configurada.
- **`sshpass` não existe** no ambiente de desenvolvimento (Windows). Para
  automação não interativa use **`plink`** (PuTTY, em
  `C:\Program Files\PuTTY\plink.exe`) ou **Python + paramiko**. Para copiar
  arquivos, `pscp`.
- O WP-CLI do servidor deve ser chamado com limite de memória explícito:
  `php -d memory_limit=512M /usr/local/bin/wp …`

Regras de conduta sobre esse acesso — o que nunca é feito sem consentimento
explícito, e o que nunca entra no repositório — estão em
[15 — Segurança e segredos](15-seguranca-e-segredos.md).

---

## Armadilha de deploy: o diretório que começa com ponto

O tema depende de `assets/dist/.vite/manifest.json`. **`.vite` começa com
ponto**, e muitos clientes FTP (o FileZilla entre eles, em configuração padrão)
**não enviam arquivos ocultos**. Sem o manifest, o tema perde CSS e JS.

Na instalação real o `.zip` foi usado justamente por isso, e a sobrevivência do
manifest (204 bytes) foi **verificada no servidor** após a extração. O MD5 do
pacote foi conferido local *versus* remoto antes de extrair.

O tema tem uma proteção: sem manifest, cai para `style.css` — o site abre, mas
sem o layout correto. Isso é rede de segurança, não solução. Procedimento
completo em [DEPLOY.md](../DEPLOY.md) e
[14 — Runbook](14-runbook-operacional.md).
