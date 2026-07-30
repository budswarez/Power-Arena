# 14 — Runbook operacional

Procedimentos. Cada um com **pré-condição**, **passos**, **como verificar** e,
onde faz sentido, **como voltar atrás**.

Este documento é o "como fazer". O [DEPLOY.md](../DEPLOY.md) é a versão escrita
para quem vai publicar o tema pela primeira vez e não conhece o projeto — os dois
não se contradizem; este tem mais contexto de operação.

---

## Publicar uma nova versão do tema

**Pré-condição:** suíte verde (`OK (353 tests, 1511 assertions)`), árvore de
trabalho limpa, e backup do site feito.

### 1. Gerar o pacote

```bash
cd wp-content/themes/arena
bash bin/package.sh            # saída: renew/entrega-arena/arena-tema-v<versão>-<data>.zip
```

O script faz, nesta ordem: `npm run build` → **aborta se o manifest do Vite não
existir** → monta um *staging* só com o que roda em produção → compacta.

Dois detalhes que são decisões, não acidentes:

- **a saída fica FORA da raiz web** (`renew/entrega-arena/`). O padrão anterior
  gravava dentro do `public_html`, o que deixaria o distribuível publicamente
  baixável;
- o pacote **inclui** `assets/dist/.vite/` (oculto, essencial) e **exclui**
  `node_modules/`, `vendor/`, `tests/`, `.superpowers/`, configs de build e a
  pasta `docs/` do projeto.

### 2. Enviar para o servidor

> **Nesta hospedagem só `public_html` e abaixo são graváveis.** Não existe
> diretório temporário fora da raiz web. Portanto: suba para um diretório
> temporário **dentro** de `themes/`, e **apague-o depois**.

```bash
# enviar (senha; ver 15 — Segurança sobre como NÃO deixar a senha no histórico)
pscp arena-tema-v0.1.0-AAAAMMDD.zip usuario@host:/caminho/public_html/wp-content/themes/.tmp-envio/

# no servidor: conferir integridade ANTES de extrair
md5sum arena-tema-v0.1.0-AAAAMMDD.zip     # deve bater com o md5 local
unzip -q arena-tema-v0.1.0-AAAAMMDD.zip
```

### 3. Verificar antes de ativar

```bash
# o arquivo que começa com ponto sobreviveu?
ls -l wp-content/themes/arena/assets/dist/.vite/manifest.json

# sintaxe sob o PHP real da produção
find wp-content/themes/arena -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors'

# o WordPress reconhece os dois temas?
php -d memory_limit=512M /usr/local/bin/wp theme list
```

Esperado: `arena` e `arena-child` listados. Se o `manifest.json` **não** estiver
lá, **pare** — o tema cai no fallback de `style.css` e o site abre sem layout.
Reenvie pelo `.zip` (o zip preserva arquivos ocultos; o FTP frequentemente não).

### 4. Apagar os restos

```bash
rm -rf wp-content/themes/.tmp-envio
```

**Não deixe o `.zip` num caminho servido pela web.**

### 5. Ativar e limpar cache

```bash
php -d memory_limit=512M /usr/local/bin/wp theme activate arena-child
php -d memory_limit=512M /usr/local/bin/wp litespeed-purge all
php -d memory_limit=512M /usr/local/bin/wp autoptimize clear
```

Ordem importa: **LiteSpeed → Autoptimize → CDN** (se houver).

### 6. Conferir no site público

```bash
curl -sI https://www.pichauarena.com.br/                       # 200
curl -s  https://www.pichauarena.com.br/ | grep -c '\[bs-'     # 0 (nenhum shortcode cru)
curl -s  https://www.pichauarena.com.br/ | grep -c '<h1'       # 1
curl -s  https://www.pichauarena.com.br/ | grep -c 'googleapis' # 0
curl -sI https://www.pichauarena.com.br/pagina-que-nao-existe/ # 404 de verdade
```

E na matéria: breadcrumb presente, comentários carregando, relacionadas,
anúncios. Checklist completo no
[DEPLOY.md](../DEPLOY.md#checklist-de-validação-no-preview).

---

## Rollback

**Quando:** qualquer coisa grave no site público após a ativação.

```bash
php -d memory_limit=512M /usr/local/bin/wp theme activate publisher-child
php -d memory_limit=512M /usr/local/bin/wp litespeed-purge all
php -d memory_limit=512M /usr/local/bin/wp autoptimize clear
```

**A troca de tema não altera conteúdo.** Posts, páginas, menus e widgets
permanecem intactos — por isso o rollback é seguro e imediato. É também por isso
que o Publisher continua instalado, apenas desativado.

Único efeito colateral conhecido: os widgets movidos para `arena-primary` voltam
a ficar "inativos" da perspectiva do Publisher, e vice-versa. Nenhum é perdido.

---

## Preview por token: quando usar (e quando não)

**Para que serve.** Ver o Arena no servidor real, com conteúdo real e plugins
reais, **enquanto os visitantes continuam no tema antigo**. Foi projetado para o
período *antes* da primeira ativação.

**Instalação.**

1. copiar `mu-plugins/arena-preview.php` para `wp-content/mu-plugins/`;
2. no `wp-config.php`, antes de `/* That's all, stop editing! */`:

```php
define('ARENA_PREVIEW_TOKEN', 'um-token-secreto-e-longo');
// opcional: previsualizar o tema FILHO
// define('ARENA_PREVIEW_STYLESHEET', 'arena-child');
```

3. acessar `https://www.pichauarena.com.br/?arena_preview=<token>`.

**Proteções embutidas:** o token precisa existir, ser string e não ser vazio;
comparação com `hash_equals`; entrada em forma de array resulta em `null` (falha
fechada); a resposta do preview leva `X-Robots-Tag: noindex, nofollow` + cabeçalhos
de `no-cache` + `<meta robots>`. Uma requisição normal não leva nada disso.

> ### Quando **não** usar
>
> **Depois que o Arena está ativo, não instale.** O mu-plugin faz todo
> administrador logado ver o tema definido nele — ou seja, o efeito se inverte: os
> administradores veriam o tema **antigo**. É por isso que ele **não está
> instalado em produção** hoje, e está apenas guardado (inerte) em
> `themes/arena/mu-plugins/`.
>
> Lembre também que `wp-content/mu-plugins` é um **symlink** para a árvore
> gerenciada da Hostinger — ver
> [09 — Infraestrutura](09-infraestrutura-producao.md#mu-plugins-não-é-seu).

**Cache:** se o preview mostrar o tema antigo, é cache. Teste logado como
administrador (o LiteSpeed não cacheia usuários logados) ou purgue o LiteSpeed.

---

## Limpar caches

```bash
wp litespeed-purge all
wp autoptimize clear
```

Pelo painel: *LiteSpeed Cache → Purge All*, depois *Autoptimize → Delete Cache*.
CDN/Cloudflare, se houver, por último.

**Sempre nessa ordem.** O Autoptimize gera os arquivos agregados; o LiteSpeed
guarda a página que os referencia. Limpar o LiteSpeed depois garante que a página
cacheada não aponte para um agregado que deixou de existir.

Para saber **quem** está otimizando o quê antes de mexer, existe um diagnóstico
somente-leitura (`cache-diag.php`) que imprime as options relevantes dos dois
plugins, a presença de `advanced-cache.php`/`object-cache.php`, o valor de
`WP_CACHE`, o `SERVER_SOFTWARE` e a lista de plugins de cache ativos.

---

## Backups antes de qualquer alteração de banco

**Regra:** nada é removido do banco sem backup **verificado** antes.

O script `fazer-backups.sh` faz isso com um detalhe que vale copiar em qualquer
script novo: **a senha do banco nunca aparece na linha de comando**. Ela é lida do
`wp-config.php` e escrita num arquivo temporário com `chmod 600`, passado ao
`mysqldump` via `--defaults-extra-file`, e **apagado no fim**:

```sh
mysqldump --defaults-extra-file=/tmp/.arena-my.cnf \
  --no-tablespaces --single-transaction --quick "$DB" <tabela> | gzip -6 > "$B/<arquivo>.sql.gz"
```

Motivo: argumentos de processo são visíveis para outros usuários do servidor
(`ps`). Ver [15 — Segurança e segredos](15-seguranca-e-segredos.md).

---

## Limpeza de banco: como foi feita

Procedimento executado em produção, com autorização explícita. Registrado aqui
porque a **ordem** é o que torna a operação segura.

**Passo 0 — portão de integridade.** Antes de remover qualquer coisa, cada
backup é testado:

```sh
gzip -t "$f"                          # o arquivo abre?
zcat "$f" | grep -c 'INSERT INTO'     # tem conteúdo?
```

**Se qualquer backup falhar, o script aborta** — não é aviso, é `exit 1`.

**Passos 1–7 — remoções**, cada uma com backup correspondente:

| # | O que | Observação |
|---|---|---|
| 1 | `wp-content/object-cache.php` | drop-in órfão; copiado para `.bak` antes |
| 2 | transients | regenerável |
| 4 | 5 plugins fora de produção | **desativados**, não removidos |
| 5 | `wp_postmeta_bkp_20260724` (**287 MB**) | tabela de backup esquecida |
| 6 | `postmeta` de plugins que saíram + 8 tabelas `wp_yoast_*` | **as metas `_yoast_wpseo_*` foram preservadas** — são conteúdo |
| 7 | `oembed_cache` (posts + meta), tabelas do Check & Log Email | regenerável / plugin desinstalado |

O **item 3 (EWWW) foi deliberadamente não tocado.**

**Passo final — cache e conferência do estado:**

```sh
wp litespeed-purge all ; wp autoptimize clear
wp db query "SELECT COUNT(*) FROM wp_options"
wp db query "SELECT ROUND(SUM(data_length+index_length)/1048576,1) AS banco_mb
             FROM information_schema.TABLES WHERE table_schema=DATABASE()"
wp plugin list --status=active --format=count
```

> **Ruído esperado:** o WP-CLI em PHP 8.5 imprime avisos de *deprecation* da
> própria biblioteca embutida. Os scripts filtram com
> `grep -viE 'deprecated|^warning'` justamente por isso — não confunda com erro.

---

## Reconstruir os assets

Só é necessário se você alterou CSS ou JS:

```bash
cd wp-content/themes/arena
npm install
npm run build     # gera assets/dist/ + assets/dist/.vite/manifest.json
```

`npm run dev` sobe o Vite em modo de desenvolvimento. Detalhes em
[05 — Build e assets](05-build-e-assets.md).

---

## Rodar a suíte de testes

```bash
MSYS_NO_PATHCONV=1 npx wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit
```

Esperado: `OK (353 tests, 1511 assertions)`.

> **Quirk conhecido:** na fase vermelha, este ambiente às vezes imprime
> `syntax error, unexpected identifier "SERIALIZATION_FORMAT_USE_UNSER..."` em vez
> da mensagem real — é bug de renderização do exportador do PHPUnit. **Julgue por
> contagem de falhas e código de saída**, não pela mensagem. História completa em
> [Erro 1](11-diario-de-bordo.md#erro-1--desenvolvi-na-versão-errada).

---

## Diagnóstico rápido: sintoma → primeiro lugar para olhar

| Sintoma | Olhe primeiro |
|---|---|
| site sem estilo, layout "cru" | `assets/dist/.vite/manifest.json` existe no servidor? |
| breadcrumb desapareceu | `Arena\Seo::breadcrumbProvider()` — se devolver `nenhum`, é o plugin |
| tela de widgets não salva | [09 — rota `/batch/v1`](09-infraestrutura-producao.md#a-rota-batchv1-é-removida-pela-plataforma) e o caso wpDiscuz |
| sidebar vazia | widgets ainda em "Widgets Inativos" — [13 — Pendências](13-pendencias.md#ação-manual-no-site-widgets-da-sidebar) |
| `[bs-…]` aparecendo como texto | WPBakery (`js_composer`) inativo ou ausente |
| imagem centralizada não centraliza | regra de alinhamento voltou para dentro de `@layer` — [ADR-007](12-decisoes-arquiteturais.md#adr-007--css-em-layer--e-o-preço-disso) |
| mudança no CSS não aparece | cache: LiteSpeed → Autoptimize, nessa ordem |
| "não acho as opções do tema" | *Aparência → Personalizar → Arena*, e o menu "Arena" no admin — [07](07-opcoes-e-painel.md) |
