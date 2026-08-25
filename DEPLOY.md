# Arena — Instruções de Instalação e Publicação

Guia para subir o tema **Arena** no servidor do `powerarena.example.com` e validá-lo
**sem afetar os visitantes**, com procedimento de rollback.

---

## 0. Resumo em uma linha

Suba os temas → valide com o preview por token (site continua no Publisher) →
só então ative o Arena → limpe os caches.

---

## 1. O que vem no pacote

```
arena/                 ← tema pai (o tema em si)
arena-child/           ← tema filho (para customizações futuras seguras)
mu-plugins/
  arena-preview.php    ← habilita o preview por token (opcional, mas recomendado)
DEPLOY.md              ← este arquivo
README.md              ← documentação de desenvolvimento
```

## 2. Requisitos no servidor

| Item | Versão esperada | Observação |
|---|---|---|
| WordPress | **7.0.2+** | é a versão da produção atual |
| PHP | **8.5** (mínimo 8.1) | é a versão da produção atual |
| WPBakery (`js_composer`) | 8.7+ | **obrigatório** — a home é montada com ele |
| Advanced Custom Fields | **opcional** | painel "Arena" extra — **o tema NÃO precisa dele**, ver seção 6 |
| Yoast SEO | já instalado | o tema **não** emite SEO próprio; o Yoast continua no controle |

> O tema **não** requer o Publisher, nem o ACF. Mas mantenha o Publisher
> instalado (desativado) até concluir a validação, para permitir rollback
> imediato.
>
> **Nota da produção real:** o ACF **não está instalado** em
> `powerarena.example.com` hoje. Isso é esperado e suportado — a partir desta
> versão, o Customizer nativo (*Aparência → Personalizar*) é o lugar
> principal para configurar o tema, sem precisar de nenhum plugin. Ver
> seção 6.

---

## 3. ⚠️ ATENÇÃO CRÍTICA ao enviar por FTP/SFTP

O tema usa assets compilados em:

```
arena/assets/dist/
arena/assets/dist/.vite/manifest.json   ← ESTE ARQUIVO É ESSENCIAL
```

**`.vite` começa com ponto.** Muitos clientes FTP (incluindo o FileZilla, por
padrão em algumas configurações) **ocultam e não enviam arquivos/pastas que
começam com ponto**.

Se o `manifest.json` não chegar ao servidor, o tema perde o CSS e o JS
(o site aparece sem estilo).

**Como evitar:**
1. No FileZilla: **Servidor → Forçar exibição de arquivos ocultos** (ativar).
2. Após o upload, confirme no servidor que existe:
   `wp-content/themes/arena/assets/dist/.vite/manifest.json`
3. Alternativa mais segura: envie o **.zip** e descompacte pelo gerenciador de
   arquivos da Hostinger (o zip preserva arquivos ocultos).

> O tema tem uma proteção: se o manifest faltar, ele carrega o `style.css` como
> reserva para não ficar totalmente sem estilo — mas o layout **não** ficará
> correto. Confirme o manifest.

---

## 4. Instalação

1. **Backup primeiro.** Faça backup do site (você já tem o plugin de backup
   instalado) antes de qualquer alteração.
2. Envie as pastas para `wp-content/themes/`:
   - `wp-content/themes/arena/`
   - `wp-content/themes/arena-child/`
3. **Não ative nada ainda.**
4. Verifique em *Aparência → Temas* que "Arena" e "Arena Child" aparecem.

---

## 5. Validação com preview (site continua no Publisher)

Isso permite ver o Arena **no servidor real, com o conteúdo real**, enquanto
todos os visitantes continuam vendo o site atual.

1. Envie `mu-plugins/arena-preview.php` para `wp-content/mu-plugins/`
   (crie a pasta `mu-plugins` se não existir — ela ativa automaticamente).
2. Edite o `wp-config.php` e adicione, **antes** da linha
   `/* That's all, stop editing! */`:

   ```php
   define('ARENA_PREVIEW_TOKEN', 'troque-por-um-token-secreto-longo');
   // Opcional: previsualizar o tema FILHO em vez do pai
   // define('ARENA_PREVIEW_STYLESHEET', 'arena-child');
   ```

3. Acesse com o token:
   `https://www.powerarena.example.com/?arena_preview=troque-por-um-token-secreto-longo`
4. Navegue: home, uma matéria, uma categoria, uma busca, uma URL inexistente (404).
5. Administradores logados veem o Arena **sem** precisar do token.

**Proteções já incluídas:** respostas do preview enviam
`X-Robots-Tag: noindex, nofollow` e cabeçalhos de *no-cache*, para o Google não
indexar o preview e o LiteSpeed não cacheá-lo.

**Cache:** se o preview aparecer com o tema antigo, é cache. Faça o teste
logado como administrador (o LiteSpeed não cacheia usuários logados) ou limpe o
cache do LiteSpeed.

### Checklist de validação no preview

- [ ] Home com os blocos do WPBakery (destaques, colunas de categoria, últimas)
- [ ] Matéria: imagem, título, autor/data, caixa "Resumo da matéria", tags, relacionadas
- [ ] **Breadcrumb do Yoast** aparecendo acima do conteúdo
- [ ] **Comentários (wpDiscuz)** carregando na matéria
- [ ] **Anúncios** (ad-inserter) aparecendo onde deviam
- [ ] Categoria: destaque no topo, listagem, paginação (página 2 com posts diferentes)
- [ ] Busca e 404 funcionando
- [ ] Menu no celular (botão de menu abre o painel lateral)
- [ ] Sem erros no console do navegador

---

## 6. Configuração do tema (sem precisar do ACF)

Após ativar (passo 7) ou já durante o preview logado — **clique exatamente
aqui**:

1. **Logo** — *Aparência → Personalizar → Identidade do site* (é o
   Customizer NATIVO do WordPress, não uma tela própria do tema). Envie a
   versão em alta resolução (2×): `logo-powerarenaarena-v2-retina.png` (640×140).
   Funciona **mesmo sem nenhum plugin instalado** — é este exatamente o
   caminho que faltava antes desta versão ("não achei onde altero
   configurações do tema, como adicionar a logo").
2. **Cor de destaque / posição da sidebar / fonte base** — *Aparência →
   Personalizar → **Arena*** (painel próprio do tema, com uma seção "Opções
   do Arena" e uma descrição em cada controle). Se escolher uma cor
   personalizada, o tema **calcula automaticamente** uma variante escura
   para textos, garantindo contraste acessível (WCAG AA) — mesma garantia
   valendo aqui e no painel ACF (item 6.1 abaixo).
3. **Menus** — *Aparência → Menus* (ou *Personalizar → Menus*). O tema
   registra os mesmos locais do Publisher, então as atribuições atuais são
   herdadas. Cada nome diz **onde** o menu aparece:

   | Local | Nome no painel | Onde aparece |
   |---|---|---|
   | `main-menu` | Menu Principal (cabeçalho) | a barra de menu do cabeçalho |
   | `top-menu` | Menu Superior (barra escura) | a faixa escura acima do logo |
   | `resp-menu` | Menu Mobile (painel lateral) | o painel que abre no celular |
   | `footer-menu` | Menu do Rodapé | rodapé — **opcional** |

   O menu do **cabeçalho** é o `main-menu` ("Menu Principal (cabeçalho)").
   Se `resp-menu` não tiver nada atribuído, o painel mobile reaproveita o
   `main-menu`; se `footer-menu` estiver vazio, o rodapé também usa o
   `main-menu` — é o comportamento de sempre.
4. **Widgets — ⚠️ AÇÃO MANUAL NECESSÁRIA.** *Aparência → Widgets* → área
   **Sidebar Principal** (`arena-primary`). **O Publisher guarda os widgets
   da sidebar numa área própria dele (`primary-sidebar`) — a troca de tema
   NÃO migra esses widgets automaticamente** (é um comportamento padrão do
   WordPress: widgets de uma área que some do tema ativo ficam "inativos",
   preservados, mas não aparecem em lugar nenhum). **Sem este passo, a
   coluna lateral aparece vazia/oculta** em matérias, páginas e categorias.
   Para corrigir:
   - Vá em *Aparência → Widgets*.
   - Se existir uma seção **"Widgets Inativos"**, os widgets antigos do
     Publisher (`primary-sidebar`) estarão lá — arraste cada um de volta
     para **Sidebar Principal** (`arena-primary`).
   - Se a seção de inativos não aparecer, recrie os widgets manualmente
     (a lista de posts recentes, categorias, etc. que estavam na sidebar
     antiga) direto em **Sidebar Principal**.
   - Isto é **intencional, não um bug**: a migração de widgets é um estado
     controlado pelo dono do site, o tema não tenta adivinhar/mover nada
     sozinho.

### 6.1 Painel "Arena" do ACF (opcional, só se o plugin estiver instalado)

Se o site tiver o plugin **Advanced Custom Fields** ativo, um painel
"Arena" adicional aparece no menu do admin, com os mesmos 4 campos (logo,
cor, fonte, sidebar) — útil como alternativa, mas **não é necessário**: o
Customizer acima (passos 1-2) já cobre tudo, sem plugin nenhum. Quando
ambos estão definidos, o valor do Customizer sempre vence.

---

## 7. Publicação (ir ao ar)

1. *Aparência → Temas* → ativar **Arena** (ou **Arena Child**, se pretende
   customizar; recomendado ativar o filho).
2. Limpar caches, nesta ordem:
   - LiteSpeed Cache → *Purge All*
   - Autoptimize → *Delete Cache*
   - Se usar CDN/Cloudflare, purgar também
3. Conferir no site público: home, uma matéria, uma categoria.
4. Rodar uma verificação de SEO: confirmar que o Yoast segue emitindo
   title/description/schema e o sitemap responde.

---

## 8. Rollback (se algo der errado)

1. *Aparência → Temas* → ativar **Publisher Child** novamente.
2. Limpar LiteSpeed + Autoptimize.
3. O site volta ao estado anterior. Nenhum conteúdo é alterado pela troca de
   tema — posts, páginas, menus e widgets permanecem intactos.

---

## 9. O que **NÃO** deve ir para o servidor

Se você copiar a pasta de desenvolvimento em vez do zip, **não envie**:

```
node_modules/     vendor/        .git/
tests/            .superpowers/  .wp-env.json    .wp-env.override.json
composer.json     composer.lock  package.json    package-lock.json
vite.config.js    phpunit.xml.dist
```

E, fora do tema, **não envie** a pasta `docs/` do projeto.

O `.zip` gerado já exclui tudo isso — contém apenas o que o site precisa em
tempo de execução (templates, `inc/`, `assets/dist/`, `assets/fonts/`,
`assets/img/`, `languages/`, `template-parts/`, `style.css`, `theme.json`).

---

## 10. Observações técnicas úteis

- **Fontes locais.** Barlow e Oswald são servidas pelo próprio tema
  (`assets/fonts/`), sem chamadas ao Google Fonts — melhor desempenho e sem
  enviar IP de visitantes a terceiros (LGPD). Licença SIL OFL incluída.
- **Blocos do Publisher.** Os quatro blocos de listagem usados na home
  (`bs-modern-grid-listing-7`, `bs-mix-listing-3-1`, `bs-blog-listing-1`,
  `bs-grid-listing-1`) e a caixa `[accordions]/[accordion]` ("Resumo da matéria",
  presente em ~96% das matérias) foram **reimplementados** no Arena. As matérias
  e a home existentes continuam funcionando sem edição.
- **Editável no WPBakery.** Os blocos aparecem no editor visual (categoria
  "Arena"), então a redação continua editando a home como antes.
- **Tema filho.** Faça customizações em `arena-child` para que atualizações do
  tema pai não sobrescrevam seu trabalho.
- **Acessibilidade.** O tema pontua 100 em acessibilidade no Lighthouse
  (verificado em artigo/mobile e categoria/desktop).

---

## 11. Se precisar reconstruir os assets

Só é necessário se você alterar CSS/JS do tema:

```bash
cd wp-content/themes/arena
npm install
npm run build      # gera assets/dist/ + assets/dist/.vite/manifest.json
```

Detalhes de ambiente de desenvolvimento estão no `README.md`.
