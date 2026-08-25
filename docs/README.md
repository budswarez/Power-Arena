# Documentação do tema Arena

Índice da documentação do projeto. Comece por onde a sua necessidade estiver.

## Quero...

| Necessidade | Leia |
|---|---|
| Entender o que é este projeto e por que ele existe | [01 — Visão geral](01-visao-geral.md) |
| Rodar o projeto na minha máquina | [03 — Ambiente local](03-ambiente-local.md) |
| Entender como o código está organizado | [02 — Arquitetura](02-arquitetura.md) |
| Rodar ou escrever testes | [04 — Testes](04-testes.md) |
| Mexer em CSS/JS e gerar o build | [05 — Build e assets](05-build-e-assets.md) |
| Entender os blocos `[bs-*]` da home | [06 — Blocos e shortcodes](06-blocos-e-shortcodes.md) |
| Adicionar uma opção no painel do tema | [07 — Opções e painel](07-opcoes-e-painel.md) |
| Saber como o tema conversa com os plugins | [08 — Compatibilidade com plugins](08-compatibilidade-plugins.md) |
| Entender as peculiaridades do servidor de produção | [09 — Infraestrutura de produção](09-infraestrutura-producao.md) |
| Publicar uma nova versão no site | [../DEPLOY.md](../DEPLOY.md) e [14 — Runbook](14-runbook-operacional.md) |
| Ver números de performance e como foram medidos | [10 — Medições](10-medicoes-e-performance.md) |
| Entender por que uma decisão foi tomada assim | [12 — Decisões arquiteturais](12-decisoes-arquiteturais.md) |
| Saber o que aconteceu no projeto, em ordem | [11 — Diário de bordo](11-diario-de-bordo.md) |
| Saber o que falta fazer | [13 — Pendências](13-pendencias.md) |
| Lidar com senhas, chaves e acessos | [15 — Segurança e segredos](15-seguranca-e-segredos.md) |
| Subir a versão e publicar uma release | [16 — Versionamento](16-versionamento.md) e [../CHANGELOG.md](../CHANGELOG.md) |
| Planejar e executar as próximas melhorias | [17 — Roadmap de melhorias](17-roadmap-melhorias.md) |

## Mapa completo

```
docs/
├── README.md                       ← este arquivo
├── 01-visao-geral.md               o que é, para quem, escopo e não-escopo
├── 02-arquitetura.md               classes, templates, fluxo de dados
├── 03-ambiente-local.md            wp-env, Docker, conteúdo de teste
├── 04-testes.md                    PHPUnit, o que cada suíte cobre, armadilhas
├── 05-build-e-assets.md            Vite, manifest, fontes, CSS em camadas
├── 06-blocos-e-shortcodes.md       os 4 blocos do Publisher reimplementados
├── 07-opcoes-e-painel.md           Settings, AdminPanel, Customizer, tokens CSS
├── 08-compatibilidade-plugins.md   Rank Math, WPBakery, LiteSpeed, EWWW, wpDiscuz
├── 09-infraestrutura-producao.md   Hostinger h5g: o que essa hospedagem faz de diferente
├── 10-medicoes-e-performance.md    Core Web Vitals, metodologia, números
├── 11-diario-de-bordo.md           histórico cronológico: decisões, bugs, causas-raiz
├── 12-decisoes-arquiteturais.md    ADRs — cada escolha e o motivo
├── 13-pendencias.md                aberto, com contexto suficiente para retomar
├── 14-runbook-operacional.md       procedimentos: deploy, rollback, limpeza, backup
├── 15-seguranca-e-segredos.md      o que nunca entra no repositório
├── 16-versionamento.md             SemVer, onde vive a versão, procedimento de release
├── 17-roadmap-melhorias.md         cronograma priorizado e critérios de conclusão
└── historico/                      specs, planos e reconhecimento originais
```

## Convenções desta documentação

**Números têm procedência.** Onde aparece uma medição (LCP de 888 ms, 19 MB de
`llms.txt`, 101 MB de memória por requisição), o documento diz **como** foi
medida. Medição sem método é opinião.

**Erros ficam registrados.** O [diário de bordo](11-diario-de-bordo.md) inclui
os diagnósticos errados e o que os corrigiu. Isso é deliberado: saber que uma
hipótese foi testada e descartada evita que alguém a persiga de novo.

**Comentários no código explicam o "por quê".** A documentação explica o
"onde" e o "como"; a razão de uma linha específica existir mora no comentário
ao lado dela. Quando os dois divergirem, o código é a verdade — e a
documentação é o bug.
