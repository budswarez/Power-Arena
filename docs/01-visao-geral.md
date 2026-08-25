# 01 — Visão geral

## O que é

**Arena** é um tema WordPress escrito do zero para o portal de notícias
[powerarena.example.com](https://www.powerarena.example.com), substituindo o tema
comercial **Publisher** (BetterStudio).

Não é uma customização do Publisher nem um tema filho dele. É código novo, com
o objetivo de reproduzir a experiência visual e funcional do site anterior sem
carregar o peso da base original.

## Por que existe

Quatro motivos, na ordem em que pesaram na decisão:

1. **Sair da dependência da BetterStudio** — licença, atualizações e roadmap de
   um fornecedor externo.
2. **Performance** — o Publisher entregava 734 KB só de CSS do tema. O Arena
   entrega 45 KB de CSS+JS mais 224 KB de fontes servidas localmente (medição e
   método em [10 — Medições](10-medicoes-e-performance.md#peso-dos-assets-do-tema)).
3. **Manter a redação produzindo sem retreinamento** — a home continua sendo
   montada no WPBakery, com os mesmos blocos de listagem de antes.
4. **Possibilidade de revenda** — o tema é próprio, então pode virar produto.

## Restrição fundamental do projeto

> **O tema Publisher não foi modificado em nenhum momento.** Nem um arquivo,
> nem uma linha. Toda a compatibilidade foi obtida reimplementando o
> comportamento observado, nunca copiando código.

Isso é uma exigência de projeto (e de licença). A técnica usada está descrita em
[12 — Decisões arquiteturais](12-decisoes-arquiteturais.md#adr-001--reimplementação-limpa-clean-room):
medir a saída renderizada e os valores computados de CSS no site público, e
escrever código próprio que produza o mesmo resultado.

## O que o tema entrega

| Área | Situação |
|---|---|
| Home montada no WPBakery, com os 4 blocos de listagem do Publisher | funcionando |
| Matéria (single), páginas, categorias/tags, busca, 404, anexo | funcionando |
| Menu mega-menu, menu superior, painel mobile off-canvas | funcionando |
| Painel de opções próprio no admin + Customizer | funcionando |
| Sidebar com widgets, comentários, paginação | funcionando |
| Acessibilidade | 100 no Lighthouse (duas combinações de viewport/template verificadas) |
| Core Web Vitals | LCP 888 ms, CLS 0.0000, INP < 16 ms — ver [10 — Medições](10-medicoes-e-performance.md) |
| Suíte de testes | 372 testes, 1.599 asserções |

## O que o tema deliberadamente **não** faz

Saber o que está fora do escopo evita retrabalho e diagnósticos errados.

- **Não emite SEO próprio.** Nem `<title>`, nem meta description, nem
  Open Graph, nem JSON-LD, nem breadcrumb próprio. Isso é responsabilidade do
  plugin de SEO (hoje Rank Math). O tema apenas *chama* o breadcrumb do plugin
  ativo, através de `Arena\Seo`. Motivo: duas fontes emitindo as mesmas tags
  gera duplicidade, e o plugin faz isso melhor.
- **Não faz cache.** Nem de página, nem de objeto, nem de fragmento. Quem
  cacheia é o LiteSpeed Cache.
- **Não otimiza imagens.** Quem converte para WebP é o EWWW; quem entrega é o
  LiteSpeed.
- **Não gerencia anúncios.** Quem faz é o Ad Inserter.
- **Não substitui o WPBakery.** A home continua sendo um layout do WPBakery; o
  tema fornece os blocos que ela usa.
- **Não usa jQuery no front-end.** O JavaScript do tema é ES6+ puro, 3,5 KB,
  com `defer`.

## Público desta documentação

Escrita para **você mesmo em seis meses**, ou para quem herdar o projeto. Assume
familiaridade com WordPress e PHP moderno, e **não** assume memória do que foi
conversado durante o desenvolvimento — por isso o
[diário de bordo](11-diario-de-bordo.md) existe.

## Ambiente de produção

| Item | Versão |
|---|---|
| WordPress | 7.0.2 |
| PHP | 8.5.8 |
| Servidor | LiteSpeed (stack gerenciada Hostinger "h5g") |
| Banco | MariaDB |
| Tema ativo | `arena-child` (filho de `arena`) |

A stack da Hostinger tem comportamentos próprios que já custaram horas de
diagnóstico — todos documentados em
[09 — Infraestrutura de produção](09-infraestrutura-producao.md). **Leia esse
documento antes de investigar qualquer coisa que "deveria funcionar e não
funciona" no servidor.**
