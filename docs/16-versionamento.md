# 16 — Versionamento e releases

Como o tema é versionado, onde o número vive, e o procedimento para publicar uma
versão nova. Existe porque um tema em produção tem uma responsabilidade extra: a
atualização **não pode mudar a aparência de um site sem aviso**.

---

## Política: SemVer

`MAIOR.MENOR.CORREÇÃO` — por exemplo `0.2.0`.

| Parte | Sobe quando |
|---|---|
| **CORREÇÃO** (`0.2.1`) | correção de bug sem novidade e sem quebra |
| **MENOR** (`0.3.0`) | funcionalidade nova, compatível com a anterior |
| **MAIOR** (`1.0.0`) | mudança que **exige ação** de quem atualiza |

Enquanto a MAIOR é `0`, a interface é considerada em estabilização. O salto para
`1.0.0` será um ato deliberado, significando compromisso de estabilidade — não
uma consequência automática de acumular funcionalidades.

### O que conta como quebra num tema WordPress

Não é o mesmo que numa biblioteca. Aqui, **quebra é tudo que faz o site do outro
mudar de comportamento sem ele pedir**:

- remover ou renomear um `template-part` que um tema filho pode estar
  sobrescrevendo;
- mudar o **slug** de um local de menu (`main-menu`, `top-menu`, `resp-menu`,
  `footer-menu`) ou de uma área de widgets (`arena-primary`) — o site perde a
  atribuição feita pelo dono;
- renomear a chave de uma opção (`theme_mod`) — o valor configurado vira lixo
  silenciosamente;
- remover um filtro público (`arena_breadcrumb_html`) ou um shortcode `[bs-*]`;
- **passar a emitir um valor visual que antes não era emitido** — ver a garantia
  abaixo.

Elevar o mínimo de WordPress ou de PHP (`Requires at least`, `Requires PHP`)
também é quebra: instalações antigas param de poder atualizar.

### A garantia que faz atualizar ser seguro

> **Opção vazia ⇒ a variável CSS não é emitida**, e cada regra do `main.css` usa
> `var(--token, valor-de-hoje)`.

Consequência prática: **atualizar o tema não muda um pixel** até alguém escolher
um valor explicitamente. É o que permite publicar uma versão MENOR sem medo em
site com tráfego. Ver
[ADR-009](12-decisoes-arquiteturais.md#adr-009--valor-vazio-significa-padrão-do-tema).

---

## Onde o número da versão vive

São **três lugares**, e eles têm de concordar:

| Arquivo | Campo | Para que serve |
|---|---|---|
| `style.css` | `Version:` | é o que o WordPress mostra em *Aparência → Temas* |
| `arena-child/style.css` | `Version:` | versão do tema filho |
| `functions.php` | `ARENA_VERSION` | disponível em tempo de execução |

Além disso, o `bin/package.sh` **lê a versão do `style.css`** para nomear o
pacote (`arena-tema-v<versão>-<data>.zip`). Se você esquecer de subir o número, o
pacote sai com o nome da versão antiga — é o sintoma mais comum de um bump
incompleto.

O **cache-busting dos assets** usa `wp_get_theme()->get('Version')`, então **subir
a versão é o que faz o navegador do visitante buscar o CSS novo**. Não subir a
versão numa mudança de CSS é um bug de deploy, não um detalhe de organização.

---

## O CHANGELOG é obrigatório

[CHANGELOG.md](../CHANGELOG.md), formato *Keep a Changelog*, com as seções
**Adicionado / Corrigido / Alterado** e, quando houver, **Notas de atualização**.

Duas regras deste projeto:

1. **Cada item cita o commit** (`abc1234`). O changelog é índice, não substituto
   da mensagem de commit — o "porquê" mora nela.
2. **Notas de atualização dizem o que fazer**, mesmo quando a resposta é "nada".
   "Nenhuma ação obrigatória" é informação; silêncio não é.

---

## Procedimento de release

Pré-condição: suíte verde, árvore limpa, changelog escrito.

```bash
cd wp-content/themes/arena

# 1) suíte
MSYS_NO_PATHCONV=1 npx wp-env run tests-cli \
  --env-cwd=wp-content/themes/arena vendor/bin/phpunit

# 2) versão nos TRÊS lugares (conferir que concordam)
grep -n "^Version:" style.css arena-child/style.css
grep -n "ARENA_VERSION" functions.php

# 3) changelog + commit
git add -A && git commit          # mensagem descrevendo a release

# 4) tag anotada, com o resumo da versão
git tag -a v0.2.0 -m "Arena 0.2.0 — configuração sem plugins e ponte de SEO"

# 5) publicar código e tag
git push origin main
git push origin v0.2.0

# 6) pacote de distribuição (valida o manifest e aborta se faltar)
bash bin/package.sh
```

Depois, o deploy propriamente dito: [14 — Runbook](14-runbook-operacional.md#publicar-uma-nova-versão-do-tema).

> **A tag vem antes do deploy.** Assim, se o site apresentar um problema, existe
> um ponto exato no histórico correspondente ao que está no ar — e o `git diff`
> entre duas tags é a lista real do que mudou em produção.

---

## Repositório

| Item | Valor |
|---|---|
| Remoto | `https://github.com/budswarez/arena-wp` |
| Visibilidade | **privado** |
| Branch principal | `main` |
| Tags | `v<MAIOR.MENOR.CORREÇÃO>` |

### O que o repositório contém — e o que não

O `.gitignore` mantém fora: `node_modules/`, `vendor/`, `assets/dist/` (build),
`.wp-env.override.json` (caminhos e escolhas de cada máquina), `*.log`,
`.phpunit.result.cache` e relatórios avulsos de Lighthouse.

**O diretório `.superpowers/` não é rastreado.** É onde ficam capturas de tela e
**exports de conteúdo real de produção** usados durante o desenvolvimento — não é
material de distribuição nem de repositório. O `bin/package.sh` também o exclui
do pacote.

**Nenhum segredo entra no repositório.** As referências a token e senha nos
arquivos versionados são **placeholders** (`troque-por-um-token-secreto-longo`) e
a senha padrão do `wp-env` local (`password`), que não é secreta. Regras completas
em [15 — Segurança e segredos](15-seguranca-e-segredos.md).

### O tema filho mora no mesmo repositório

`arena-child/` é versionado junto com o pai, **dentro** de `themes/arena/`.
Motivo: as duas versões precisam concordar, e o estilo do filho **depende do
handle `arena-main`** registrado pelo pai — se o manifest do Vite faltar e
`arena-main` não for registrado, o WordPress **descarta silenciosamente** o estilo
do filho. Manter os dois no mesmo histórico torna essa dependência visível numa
única `git log`.

> **Armadilha real.** Existe também uma cópia **legada** em
> `themes/arena-child/` (irmã do tema pai, com `.git` próprio, de quando o filho
> era repositório separado). Ela **não é atualizada** e já entrou num pacote por
> engano: o pai saiu 0.2.0 e o filho 0.1.0. O `bin/package.sh` agora prefere
> explicitamente a cópia de dentro, avisa se usar a legada e **aborta** se as
> versões divergirem. Ver
> [13 — Pendências](13-pendencias.md#duas-cópias-do-tema-filho).
>
> No **servidor** o filho é necessariamente irmão (`themes/arena-child/`), porque
> é o que o WordPress exige — o empacotamento faz essa transposição.

---

## Histórico de versões

| Versão | Data | Marco |
|---|---|---|
| `0.1.0` | 2026-07-25 | primeira versão; enviada inativa, ativada em 26/07 |
| `0.2.0` | 2026-07-30 | configuração sem plugins, ponte de SEO, documentação completa |

Detalhe de cada uma em [CHANGELOG.md](../CHANGELOG.md); a narrativa com os erros
e as causas-raiz em [11 — Diário de bordo](11-diario-de-bordo.md).
