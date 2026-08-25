# 03 — Ambiente local

Como sair de "acabei de clonar o repositório" para "estou rodando o site e os
testes na minha máquina".

## Pré-requisitos

| Ferramenta | Versão | Para quê |
|---|---|---|
| **Docker Desktop** | atual | o `wp-env` sobe WordPress, MariaDB e PHP em contêineres |
| **Node.js** | 20.19+ ou 22.12+ | build dos assets (Vite) e o próprio `wp-env` |
| **Composer** | 2.x | dependências de desenvolvimento (PHPUnit, stubs do WordPress) |
| **Git** | 2.x | versionamento |

No Windows, o Docker precisa estar com o backend WSL2 ativo. O projeto foi
desenvolvido no Windows 11 com Git Bash e PowerShell — os comandos abaixo
funcionam nos dois (Git Bash é o recomendado, por causa do `bash bin/*.sh`).

## Instalação, do zero

```bash
git clone <URL-DO-SEU-REPOSITORIO> arena
cd arena

npm ci               # ferramentas de build + wp-env nas versões do lockfile
composer install     # PHPUnit e stubs (só desenvolvimento)
npm run build        # gera assets/dist/ — obrigatório, ver observação abaixo

npm run env:start    # sobe o WordPress (primeira vez baixa as imagens: alguns minutos)
npm run env:seed     # cria home, posts, categorias, comentários e menus locais
```

Ao final, o `wp-env` imprime os endereços:

- Site: `http://localhost:8888`
- Admin: `http://localhost:8888/wp-admin` — usuário `admin`, senha `password`
- Ambiente de testes: `http://localhost:8889`

> **`npm run build` não é opcional.** O tema lê
> `assets/dist/.vite/manifest.json` para saber quais arquivos enfileirar. Sem
> build, o tema cai num fallback que carrega apenas `style.css` — o site abre,
> mas sem estilo nenhum. Se a tela aparecer "crua", esta é a primeira coisa a
> verificar.

## O que o `.wp-env.json` define

```json
{
  "core": "WordPress/WordPress#7.0.2",
  "phpVersion": "8.5",
  "mappings": {
    "wp-content/themes/arena": ".",
    "wp-content/themes/arena-child": "./arena-child"
  },
  "plugins": ["…advanced-custom-fields…"],
  "config": { "WP_DEBUG": true, "WP_DEBUG_LOG": true, "SCRIPT_DEBUG": true }
}
```

**As versões são fixas de propósito**, e iguais às da produção: WordPress
**7.0.2** e PHP **8.5**. Uma versão em desenvolvimento diferente da produção já
gerou diagnóstico errado neste projeto (ver
[diário de bordo](11-diario-de-bordo.md#erro-1--desenvolvi-na-versão-errada)).

Note também que `core` aponta para uma **tag** (`#7.0.2`), não para um branch.
Apontar para branch fez um teste ficar intermitente, porque o WordPress mudava
por baixo entre execuções.

## Ajustes locais: `.wp-env.override.json`

Este arquivo **não é versionado** (está no `.gitignore`), porque contém caminhos
e escolhas de cada máquina. É onde entram plugins que você tem localmente e
constantes de teste. Modelo do que foi usado durante o desenvolvimento:

```json
{
  "plugins": [
    "https://downloads.wordpress.org/plugin/advanced-custom-fields.latest-stable.zip",
    "https://downloads.wordpress.org/plugin/seo-by-rank-math.latest-stable.zip",
    "../../plugins/js_composer"
  ],
  "mappings": {
    "wp-content/mu-plugins": "./mu-plugins"
  },
  "config": {
    "ARENA_PREVIEW_TOKEN": "troque-por-um-token-local"
  }
}
```

Pontos de atenção:

- **WPBakery (`js_composer`) é um plugin pago e não pode ser versionado.** Ele
  precisa vir de uma cópia sua, apontada por caminho relativo. Sem ele, a home
  renderiza os shortcodes `[vc_row]` como texto cru — o que já foi confundido
  com bug do tema.
- Depois de editar o override: `npx wp-env destroy && npx wp-env start`
  (o `restart` não relê mapeamentos novos de forma confiável).

## Comandos do dia a dia

```bash
npm run dev                  # Vite em watch: recompila CSS/JS ao salvar
npm run build                # build de produção
npm run env:start            # sobe
npm run env:stop             # para (mantém o banco)
npm run env:destroy          # apaga tudo, inclusive o banco
npm run env:seed             # popula/atualiza dados locais idempotentes
npx wp-env clean all         # limpa os bancos, mantém os contêineres

# WP-CLI dentro do contêiner
npx wp-env run cli wp plugin list
npx wp-env run cli wp theme activate arena-child
npx wp-env run cli wp option get sidebars_widgets
```

## Conteúdo para testar

Um tema de portal de notícias sem conteúdo não mostra nada de útil: os blocos da
home ficam vazios e os cards não aparecem. Duas formas de popular:

**A) Importar um WXR** (o caminho usado no projeto)

```bash
npx wp-env run cli wp plugin install wordpress-importer --activate
npx wp-env run cli wp import /var/www/html/wp-content/export.xml --authors=create
```

O arquivo precisa estar acessível dentro do contêiner — use um `mappings` no
override para expor a pasta onde ele está.

> Se for exportar da produção: **exporte apenas posts/páginas**, nunca
> usuários ou comentários, e remova e-mails de autor do XML antes. Ver
> [15 — Segurança e segredos](15-seguranca-e-segredos.md).

**B) Gerar conteúdo sintético**

```bash
npx wp-env run cli wp term create category Hardware --slug=hardware
npx wp-env run cli wp post generate --count=40 --post_type=post --post_status=publish
```

Funciona para testar layout e paginação, mas não tem imagem destacada — então a
imagem padrão dos cards aparece no lugar (o que também é útil testar).

## Montando a home

A home de produção é uma página estática montada no WPBakery. Para reproduzir:

1. Crie uma página (ex.: "Home").
2. Cole nela o conteúdo com os shortcodes dos blocos, por exemplo:

```
[vc_row][vc_column]
[bs-modern-grid-listing-7 columns="4" count="5" disable_duplicate="1"]
[bs-mix-listing-3-1 columns="3" count="6"]
[bs-blog-listing-1 columns="1" count="5"]
[bs-grid-listing-1 columns="4" count="8" bs-text-color-scheme="light"]
[/vc_column][/vc_row]
```

3. *Configurações → Leitura* → página estática → selecione a página criada.

Os atributos aceitos estão em [06 — Blocos e shortcodes](06-blocos-e-shortcodes.md).

## Verificando que está tudo certo

```bash
npm run build
npx wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit
```

Esperado: `OK (372 tests, 1599 assertions)` — o número cresce conforme novos
testes entram; o que importa é **OK**, sem falhas.

## Problemas comuns

| Sintoma | Causa provável | Solução |
|---|---|---|
| Site sem estilo nenhum | falta o build | `npm run build` |
| `[vc_row]` aparecendo como texto | WPBakery não instalado | mapeie o `js_composer` no override |
| Testes não encontram o WordPress | contêiner de testes não subiu | `npx wp-env start` e repita |
| Porta 8888 ocupada | outro `wp-env` rodando | `npx wp-env stop` no outro projeto |
| Mudança no CSS não aparece | build não rodou, ou cache do navegador | `npm run build` + recarga forçada |
| Erro de permissão no Docker (Windows) | WSL2 sem acesso ao disco | mova o projeto para dentro do WSL ou libere o compartilhamento no Docker Desktop |

## Trabalhando de mais de uma máquina

O repositório é autocontido: tudo que o tema precisa está versionado, com três
exceções — e todas são intencionais:

| Não versionado | Por que | Como obter na máquina nova |
|---|---|---|
| `node_modules/`, `vendor/` | dependências | `npm install`, `composer install` |
| `assets/dist/` | artefato de build | `npm run build` |
| `.wp-env.override.json` | caminhos e plugins pagos de cada máquina | recriar a partir do modelo acima |

Fluxo recomendado ao trocar de computador:

```bash
git clone <URL> arena && cd arena
npm install && composer install && npm run build
cp docs/exemplos/wp-env.override.exemplo.json .wp-env.override.json   # ajuste os caminhos
npx wp-env start
npx wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit
```
