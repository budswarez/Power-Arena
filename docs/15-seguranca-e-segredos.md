# 15 — Segurança e segredos

Regras sobre senhas, chaves, dados de visitantes e o que nunca entra no
repositório. São regras de conduta do projeto — não sugestões.

---

## O que nunca entra no repositório

| Item | Onde vive |
|---|---|
| Senha do SSH / do banco | só na cabeça de quem opera, ou num gerenciador de senhas |
| `ARENA_PREVIEW_TOKEN` | `wp-config.php` do servidor |
| Caminhos e escolhas da máquina local | `.wp-env.override.json` — **está no `.gitignore`** |
| Dumps de banco (`.sql`, `.sql.gz`) | fora da árvore do projeto, e fora da raiz web |
| Pacote distribuível (`.zip`) | `renew/entrega-arena/`, **fora do `public_html`** |

O `.gitignore` cobre `/node_modules/`, `/vendor/`, `/assets/dist/`,
`.wp-env.override.json`, `*.log`, `.phpunit.result.cache` e os relatórios
avulsos de Lighthouse (`/lh-*.json`).

> **Se um segredo escapar para um commit**, trocar o segredo é obrigatório —
> reescrever o histórico não basta, porque o valor já pode ter sido lido.

---

## Senhas em linha de comando: não

**Argumentos de processo são visíveis para outros usuários do servidor** (`ps`).
Numa hospedagem compartilhada isso não é hipótese.

O padrão adotado no projeto, e que deve ser copiado em qualquer script novo, está
no `fazer-backups.sh`: a credencial é lida do `wp-config.php`, escrita num arquivo
temporário com **`chmod 600`**, passada ao cliente por `--defaults-extra-file`, e
**apagada no fim**:

```sh
php -r '
require "wp-config.php";
$c = "[client]\nuser=" . DB_USER . "\npassword=\"" . DB_PASSWORD . "\"\n"
   . "host=" . (strpos(DB_HOST, ":") !== false ? strstr(DB_HOST, ":", true) : DB_HOST) . "\n";
file_put_contents("/tmp/.arena-my.cnf", $c);
chmod("/tmp/.arena-my.cnf", 0600);
'
mysqldump --defaults-extra-file=/tmp/.arena-my.cnf … | gzip -6 > backup.sql.gz
rm -f /tmp/.arena-my.cnf
```

**Nunca** `mysqldump -p"$SENHA"`.

### SSH sem chave

O servidor aceita **somente senha** — não há chave pública configurada, e
`sshpass` não existe no ambiente de desenvolvimento (Windows). Para automação
não interativa: **`plink`** (PuTTY) ou **Python + paramiko**; para copiar
arquivos, **`pscp`**.

Cuidados ao usar essas ferramentas:

- **não** deixe a senha no histórico do shell (use leitura interativa ou variável
  de ambiente de vida curta, nunca um literal na linha de comando);
- **não** grave a senha em nenhum arquivo do repositório;
- ao terminar, apague qualquer artefato temporário que a contenha.

---

## Postura em produção

Três regras que foram seguidas em todo o projeto:

1. **Leitura por padrão.** Reconhecimento do site foi feito pelo HTML público e
   por valores computados de CSS — sem tocar no servidor. Diagnósticos em
   produção usam scripts **somente leitura** (`cache-diag.php`,
   `auditar-redirects.php`, `custo-por-plugin.sh`).
2. **Escrita só com autorização explícita, e com backup verificado antes.** A
   limpeza de banco só rodou depois de o dono do site autorizar item por item, e
   o script **aborta** se qualquer backup falhar no teste de integridade — ver
   [14 — Runbook](14-runbook-operacional.md#limpeza-de-banco-como-foi-feita).
3. **O tema Publisher nunca foi modificado.** Nem um arquivo, nem uma linha.
   Restrição de licença e de projeto — ver
   [ADR-001](12-decisoes-arquiteturais.md#adr-001--reimplementação-limpa-clean-room).

### Dados de produção fora de produção

O conteúdo real foi exportado para o ambiente local **para testar com dados
verdadeiros**, com estas restrições:

- exportação **somente leitura**;
- **e-mails de autores removidos** do export;
- **comentários não exportados** (é onde mora o conteúdo de terceiros);
- **o banco completo nunca foi baixado.**

> **Regra permanente:** não baixe a base completa. Se algum dia for
> inevitável, exige **consentimento explícito** do dono do site e
> **anonimização** — o site tem usuários registrados e comentários, portanto
> dados pessoais.

Nos arquivos de desenvolvimento (`.superpowers/sdd/import/`) existem exports de
posts. São conteúdo editorial público, mas **não são material de distribuição**:
o pacote gerado por `bin/package.sh` exclui `.superpowers/` inteiro.

---

## Arquivos temporários que contêm coisas

Ficam em `/tmp` no servidor e devem ser limpos:

| Arquivo | Contém | Ação |
|---|---|---|
| `/tmp/.arena-my.cnf` | credencial do banco | apagado pelo próprio script |
| `/tmp/arena-memoria.log` | URLs requisitadas + uso de memória | **já removido** em 30/07 — ver [13](13-pendencias.md#instrumentação-temporária-de-memória--resolvido-em-3007) |
| `/tmp/arena-redirects-auditoria.json` | mapa de URLs antigas → destinos | apagar após migrar os redirecionamentos |

E na raiz web, durante um deploy: o `.zip` e o diretório temporário de envio
**devem ser apagados** assim que a extração for verificada. Um distribuível num
caminho servido pela web é código-fonte baixável por qualquer pessoa.

---

## O token do preview

Se você instalar o preview por token (ver
[14 — Runbook](14-runbook-operacional.md#preview-por-token-quando-usar-e-quando-não)):

- **token longo e aleatório.** Ele é o único obstáculo entre a internet e uma
  versão não publicada do site;
- vive no `wp-config.php`, **nunca** no repositório;
- **trocar imediatamente** se aparecer num log, num print de tela ou numa URL
  compartilhada. URLs vazam com facilidade — em referer, em histórico, em
  ferramenta de análise.

O mu-plugin já é conservador por construção: o token precisa existir, ser string
e não ser vazio; a comparação usa `hash_equals`; entrada em forma de array
resulta em `null` (**falha fechada**); e a resposta do preview leva
`X-Robots-Tag: noindex, nofollow` mais cabeçalhos de `no-cache` e `<meta robots>`,
para não ser indexada nem cacheada. Uma requisição normal não leva nada disso.

---

## Práticas de segurança dentro do tema

O que o código faz para não ser o elo fraco:

| Prática | Onde |
|---|---|
| **Nada de `$_GET` chega ao SQL.** | verificado na revisão da branch inteira |
| Escape consistente: `wp_kses_post()` para HTML de post, `esc_*` para o resto | todos os templates |
| `sanitize_hex_color()` nas cores | **bloqueia injeção de CSS** por atributo de bloco ou valor de opção |
| Valores fora de faixa são **descartados**, não truncados | evita que um valor editado à mão no banco vire `--arena-site-width: 99999px` |
| Capacidade `edit_theme_options` + **nonce por aba** | `Arena\AdminPanel` (gravação por `admin_post`) |
| Saneamento pelo esquema (`Arena\Settings`) | uma fonte só de verdade, nenhuma tela grava sem passar por ela |

---

## LGPD e terceiros

- **Fontes servidas pelo próprio site.** Barlow e Oswald vêm de
  `assets/fonts/` — **zero requisições** a `googleapis`/`gstatic`. Isso deixa de
  enviar o IP de cada visitante para um terceiro, além de ser mais rápido
  (ver [10 — Medições](10-medicoes-e-performance.md#o-ganho-de-auto-hospedar-as-fontes)).
  A licença SIL OFL acompanha os arquivos.
- **O tema não adiciona nenhum terceiro** ao carregamento das páginas. Os
  terceiros que existem (anúncios, comentários, analytics) vêm de plugins, e são
  responsabilidade de quem os configura.
- **Consentimento de cookies nos comentários:** o campo do core foi restaurado
  depois de uma versão do tema tê-lo derrubado ao sobrescrever `fields` no
  `comment_form()`. Não é detalhe cosmético — é o controle que permite ao
  visitante escolher se o navegador guarda os dados dele.

---

## `llms.txt`: exposição por conveniência

O módulo do Rank Math publica um arquivo de ~19 MB listando praticamente todo o
conteúdo do site, num único texto, para consumo de LLMs. Não há dado pessoal
ali — é conteúdo já público — mas vale a decisão consciente de **querer** ou não
oferecer o catálogo completo do site nesse formato. Ver
[13 — Pendências](13-pendencias.md#llmstxt-de-19-mb).
