# 04 — Testes

## Rodando

```bash
# suíte completa
npx wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit

# uma suíte
npx wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit --filter SettingsTest

# um teste
npx wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit \
  --filter 'SettingsTest::test_emits_no_css_tokens_when_nothing_is_configured'

# lista legível
npx wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit --testdox
```

No Git Bash sob Windows, prefixe com `MSYS_NO_PATHCONV=1` se os caminhos com
`/` forem convertidos indevidamente:

```bash
MSYS_NO_PATHCONV=1 npx wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit
```

Estado atual esperado: **`OK (372 tests, 1599 assertions)`**.

## Armadilha conhecida deste ambiente

> Quando a suíte **falha**, a saída deste ambiente às vezes vem embaralhada com
> uma mensagem `SERIALIZATION_FORMAT_...` sem relação com o erro real.
> **Julgue pelo número de falhas e pelo código de saída, não pelo texto.** Para
> descobrir *qual* teste falhou, rode com `--filter` por suíte ou use
> `--testdox`. Isso já custou tempo neste projeto.

## O que cada suíte cobre

### Núcleo e configuração

| Suíte | Cobre |
|---|---|
| `AutoloadTest` | o autoloader encontra todas as classes; nenhuma classe órfã |
| `SetupTest` | suportes do tema, locais de menu, tamanhos de imagem, sidebar, classes de `body` |
| `AssetsTest` | manifest do Vite, fallback sem manifest, preloads de fonte, `sizes` das imagens |
| `OptionsTest` | precedência Customizer → ACF → padrão; contraste acessível derivado |
| `SettingsTest` | esquema de opções, saneamento por tipo, emissão de tokens CSS |
| `AdminPanelTest` | gravação por aba, capacidade exigida, checkbox desmarcado |
| `CustomizerTest` | registro de painel, seções e controles |
| `OptionsPanelTest` | painel ACF (quando disponível) |
| `CompatibilityTest` | remoção de bloat; proteção do editor de widgets |
| `ThemeJsonTest` | `theme.json` válido e coerente com os tokens |

### Listagens e blocos

| Suíte | Cobre |
|---|---|
| `Listing/AttrsTest` | conversão/validação de atributos (puro) |
| `Listing/QueryTest` | atributos → args de `WP_Query`, com limites e faixas |
| `Listing/RendererTest` | orquestração, `disable_duplicate`, reset de postdata |
| `Listing/ModernGrid1Test` | o layout mais complexo (hero + grade) |
| `Blocks/ShortcodesTest` | os 4 shortcodes registrados e renderizando |
| `Blocks/VcMapTest` | registro no editor do WPBakery |
| `Blocks/AccordionsTest` | `[accordions]` → `<details>` |
| `Blocks/SingleImageHeadingTest` | imagem com heading colorido |

### Templates

| Suíte | Cobre |
|---|---|
| `SingleTemplateTest` | matéria: header, meta, tags, relacionadas, posição do breadcrumb |
| `ArchiveTemplateTest` | categoria/tag: H1, grid de destaque, paginação |
| `SearchTemplateTest` | busca, inclusive resultado vazio |
| `PageTemplateTest` | página com shell de 2 colunas |
| `NotFoundTemplateTest` | 404 devolvendo HTTP 404 de verdade |
| `AttachmentTemplateTest` | anexo |
| `CommentsTemplateTest` | lista e formulário de comentários |
| `BrandingTemplateTest` | logo nativo e fallback textual |
| `RelatedPostsTest` | relacionadas por categoria, sem repetir o post atual |
| `PaginationTest` | números, prev/next, página atual |
| `MenuIdDuplicationTest` | o menu renderizado duas vezes não repete `id` no HTML |
| `Menus/MegaMenuWalkerTest` | estrutura do mega-menu |
| `LayoutTest` | classes de coluna por posição de sidebar |
| `MediaTest` | thumbnails, `sizes`, imagem padrão |
| `SeoTest` | breadcrumb do plugin ativo; nenhum template chamando o plugin direto |
| `PreviewTest` | token de preview, cabeçalhos `noindex` |
| `StyleGuardTest` | **regras de CSS** que já causaram bug visual (ver abaixo) |

## `StyleGuardTest`: por que existir um teste que lê CSS

O CSS não tem cobertura automatizada de renderização neste projeto. Três bugs
visuais reais escaparam e voltaram, então as regras que os causaram passaram a
ser verificadas diretamente no arquivo-fonte:

1. **Título branco sobre fundo branco.** Um seletor `.content-container` sem
   qualificador casava tanto com o `<main>` da página quanto com o wrapper
   interno de cada card. O teste exige `main.content-container` e proíbe a forma
   nua.
2. **Faixa "Últimas notícias" ocupando a tela inteira.** A referência é boxed; um
   revisão anterior a quebrou com `width:100vw`. O teste garante que ela
   continue dentro da caixa.
3. **Overlay invisível engolindo todos os toques no mobile.** Um
   `.offcanvas-overlay { display: … }` sem escopo vencia o `[hidden]` do
   navegador. O teste exige a forma `:not([hidden])`.
4. **Alinhamento de mídia preso dentro de `@layer`.** Declarações **sem** layer
   vencem qualquer declaração **dentro** de um layer, independentemente de
   especificidade — então `margin-inline: auto` nunca era aplicado. O teste
   varre o arquivo mantendo a pilha de blocos abertos e falha se um seletor de
   alinhamento estiver aninhado numa camada.

Esses testes são frágeis por natureza (leem texto de CSS). Mantidos assim de
propósito: um teste frágil que pega um bug real vale mais que a ausência dele.

## Escrevendo um teste novo

```php
<?php
declare(strict_types=1);

use Arena\MinhaClasse;

final class MinhaClasseTest extends WP_UnitTestCase {
    public function test_descreve_o_comportamento_esperado(): void {
        $this->assertSame('esperado', MinhaClasse::metodo('entrada'));
    }
}
```

Convenções do projeto:

- Um arquivo por classe/assunto, em `tests/`, espelhando `inc/`.
- Nome do método diz **o comportamento**, não o método testado:
  `test_falls_back_to_the_classic_widgets_screen_when_the_batch_route_is_missing`
  em vez de `test_classicWidgets`.
- Quando o teste nasce de um bug real, **o docblock cita o bug e a medição**.
  Assim ninguém "simplifica" o teste sem entender o que ele protege.
- Ao re-disparar `after_setup_theme` dentro de um teste, declare
  `$this->setExpectedIncorrectUsage("add_theme_support( 'title-tag' )")` — o
  core marca essa chamada como uso incorreto depois de `wp_loaded`, e isso é
  artefato do teste, não bug do tema.

## Integração contínua

O repositório traz `.github/workflows/ci.yml`, que a cada push:

1. instala Node e Composer;
2. roda `npm run build`;
3. sobe o `wp-env`;
4. executa a suíte completa.

Isso protege o cenário de desenvolver em máquinas diferentes: se você esquecer
de rodar os testes numa delas, o push acusa.
