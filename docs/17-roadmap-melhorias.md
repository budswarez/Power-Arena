# 17 — Roadmap de melhorias

Plano incremental baseado na revisão técnica de agosto de 2026. Cada etapa tem
uma saída verificável e pode ser publicada sem depender das seguintes.

## Princípios de execução

- Nenhuma mudança começa em produção: primeiro ambiente local, CI e preview.
- Uma correção por branch/PR, com teste de regressão quando houver lógica.
- Mudança visual exige comparação mobile e desktop nos templates afetados.
- Credenciais e conteúdo real nunca entram no repositório ou nos dados de sample.

## Fase 0 — Fundação reproduzível (semana 1)

| Entrega | Critério de conclusão | Situação |
|---|---|---|
| `wp-env` fixado no projeto | clone novo executa `npm ci` e encontra `wp-env` local | concluído |
| tema filho montado pelo caminho correto | `arena-child` fica ativo qualquer que seja o nome da pasta clonada | concluído |
| dados locais idempotentes | `npm run env:seed` pode rodar duas vezes sem duplicar conteúdo | concluído |
| CI real | build e PHPUnit executam em push e pull request | concluído |

## Fase 1 — Correções de risco (semana 2)

1. Unificar a regra de tokens CSS: opções vazias não devem imprimir overrides,
   inclusive acento e fontes; adicionar teste que compara estado vazio e estado
   configurado.
2. Tornar o menu off-canvas modal de fato: conter o foco, neutralizar o fundo
   enquanto aberto e restaurar o foco ao fechar.
3. Fechar automaticamente o off-canvas ao atravessar o breakpoint de 860 px,
   removendo `offcanvas-open`, overlay e atributos de estado.
4. Criar stubs/testes das duas APIs de breadcrumb do Rank Math, incluindo
   retorno vazio e fallback para o próximo provedor.

Critério da fase: suíte verde e validação manual de teclado em mobile/desktop.

## Fase 2 — Operação e documentação (semana 3)

1. Atualizar `README.md` e `DEPLOY.md` para Rank Math e para os comandos npm.
2. Eliminar caminhos contraditórios do tema filho e consolidar a cópia canônica.
3. Adicionar checklist automatizado de release: versões pai/filho/PHP,
   manifest do Vite e conteúdo proibido no pacote.
4. Decidir sobre o alerta transitivo de segurança do `@wordpress/env`; manter a
   ferramenta apenas em desenvolvimento e acompanhar uma versão corrigida.

Critério da fase: uma pessoa em máquina limpa consegue preparar, testar e
empacotar o tema seguindo apenas a documentação.

## Fase 3 — Qualidade visual e compatibilidade (semanas 4–5)

1. Executar matriz de regressão: home, matéria, página, categoria páginas 1/2,
   busca com/sem resultado, 404 e anexo; mobile 390 px e desktop 1366 px.
2. Validar Rank Math, WPBakery, wpDiscuz, LiteSpeed/EWWW e Ad Inserter no preview.
3. Registrar budgets de performance para CSS, JS, LCP e número de imagens eager.
4. Avaliar a implementação de `tabs="cat_filter"` como funcionalidade separada,
   com endpoint, cache e acessibilidade próprios.

Critério da fase: evidências salvas por versão e nenhuma regressão crítica de
layout, acessibilidade, SEO ou cache.

## Fase 4 — Produção e manutenção contínua (mensal)

- Revisar dependências e alertas de segurança.
- Conferir se os contornos da Hostinger e do wpDiscuz ainda são necessários.
- Rodar Lighthouse em matéria mobile e categoria desktop/página 2.
- Auditar 404 e redirecionamentos históricos ainda não migrados do Yoast.
- Revisar tamanho e necessidade do `llms.txt` do Rank Math.

## Execução de performance — 0.2.9 (2026-08-01)

| Entrega | Situação | Evidência/observação |
|---|---|---|
| banner responsivo sem trocar WPBakery | concluído em produção | 121,6 → 32,3 KiB no mobile |
| candidato de 480×270 para cards | concluído em produção | variantes geradas só para cards da home |
| `unicodeRange` no `theme.json` | concluído em produção | 8 → 4 arquivos de fonte |
| CF7 condicional | concluído em produção | nenhum asset CF7 na home; shortcode preserva carga |
| OneSignal fora do caminho do LCP | concluído em produção | interação ou 4 s após `window.load` |
| consolidar GTM/GA4 | concluído pelo proprietário | um `gtag.js` e um `page_view`; GTM vazio não é mais injetado |
| cron real | pendente no hPanel | SSH sem `/var/spool/cron`; WP-Cron continua ligado |
| reduzir TTFB mobile | próxima rodada | documento ~480–740 ms; manter A/B sem mudar LiteSpeed |

Versão publicada com backup focal e smoke test. Resultado: desktop 98/LCP 1,1 s;
mobile oscilou 89–93 conforme a segunda carga de `gtag.js`, com payload reduzido
de 1.101 KiB para 730–890 KiB.

## Execução de performance — 0.2.10/0.2.11 (2026-08-01)

| Entrega | Situação | Evidência/observação |
|---|---|---|
| dois candidatos reais a LCP no hero | concluído em produção | home mobile mediana 93, LCP 2,95 s |
| variante 240×135 e `sizes` por card | concluído em produção | 99 anexos recentes regenerados; um anexo antigo ausente |
| CSS-base do WPBakery assíncrono | testado e descartado | 5× A/B: mediana 91 com e sem; não publicado |
| reCAPTCHA do wpDiscuz sob demanda | concluído em produção | matéria 1,41 MiB → 631–637 KiB; carga confirmada ao chegar aos comentários |
| validar duas matérias mobile | concluído | medianas 93, LCP 2,83–2,86 s, TBT 2 ms |
| reduzir variância de TTFB mobile | continua pendente | respostas de matéria oscilaram entre ~0,5 e 2,4 s; não alterar cache mobile sem novo A/B |

## Fluxo padrão por melhoria

1. Criar branch curta a partir de `main`.
2. Escrever ou ajustar o teste que representa o comportamento desejado.
3. Implementar a menor mudança coerente.
4. Rodar `npm run build` e `npm test`.
5. Validar visualmente no `wp-env` com os dados de sample.
6. Abrir PR; a CI precisa passar.
7. Atualizar `CHANGELOG.md`, versão e documentação quando aplicável.
8. Publicar pelo preview por token, validar e só então ativar/limpar caches.
