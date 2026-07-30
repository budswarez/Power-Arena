# Arena — Fatia 1 (Núcleo do Tema) — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar o esqueleto funcional do tema WordPress `arena` (pai + filho) — que ativa, carrega assets via Vite, expõe opções via ACF com degradação segura, e permite preview em produção — junto com um ambiente local reproduzível e testes PHPUnit passando.

**Architecture:** Tema PHP 8 clássico com arquitetura OOP (namespace `Arena\`, autoloader PSR-4 próprio, sem Composer em produção). Módulos de responsabilidade única em `inc/`, orquestrados por `Arena\Theme`. Assets compilados por Vite (manifest com hash) e enfileirados pelos hooks padrão do WordPress. Lógica testável extraída em métodos puros. Ambiente de desenvolvimento e testes via `@wordpress/env` (Docker).

**Tech Stack:** PHP 8.2 (piso 8.1), WordPress 6.4+, Node 20+, Vite 5, CSS moderno modularizado (nesting/variables/@layer) + `theme.json`, ACF (PRO ou free), `@wordpress/env`, PHPUnit 9 com a WP test suite, Composer (apenas dev: PHPUnit + WP stubs).

## Global Constraints

- **PHP:** piso 8.1, alvo 8.2. Todo arquivo PHP começa com `declare(strict_types=1);`.
- **Namespace/prefixo:** classes sob `Arena\`; funções globais, hooks, handles de asset e option keys prefixados com `arena_`/`arena-`.
- **Sem jQuery** no código próprio do tema (ES6+ Vanilla/Alpine apenas).
- **Hooks intactos:** `wp_head()` antes de `</head>` e `wp_footer()` antes de `</body>`; preservar o loop padrão (`the_post()`, `the_content()`, `wp_link_pages()`, `body_class()`, `post_class()`).
- **Caminhos:** tema pai usa `get_template_directory_uri()`; tema filho usa `get_stylesheet_directory_uri()`.
- **Reconstrução limpa:** proibido copiar código-fonte do Publisher/Better Framework para dentro do Arena.
- **Segurança operacional:** acesso SSH/SFTP à produção é **somente leitura**; nenhuma escrita em produção sem aprovação explícita. A pasta `docs/` não vai para produção. Git é inicializado **escopado à pasta do tema `arena`**, nunca sobre todo o `wp-content`.
- **CSS:** puro modularizado; sem framework utility-first.

---

## Fase A — Ambiente local & repositório

### Task 1: Ambiente local reproduzível (wp-env) + repositório do tema

**Files:**
- Create: `themes/arena/.wp-env.json`
- Create: `themes/arena/.gitignore`
- Create: `themes/arena/composer.json`

**Interfaces:**
- Consumes: nada.
- Produces: WP local rodando em `http://localhost:8888` (dev) e `http://localhost:8889` (tests); repositório git em `themes/arena/`.

- [ ] **Step 1: Verificar pré-requisitos**

Run:
```bash
node --version   # espera v20+
docker --version # espera Docker Desktop rodando
composer --version
```
Expected: todas as versões impressas sem erro. Se Docker não estiver instalado, instalar Docker Desktop para Windows antes de continuar.

- [ ] **Step 2: Instalar wp-env globalmente**

Run:
```bash
npm -g install @wordpress/env
```
Expected: instala `wp-env` no PATH.

- [ ] **Step 3: Criar `.wp-env.json`**

Arquivo `themes/arena/.wp-env.json`:
```json
{
  "core": "WordPress/WordPress#6.4",
  "phpVersion": "8.2",
  "themes": ["."],
  "plugins": [
    "https://downloads.wordpress.org/plugin/advanced-custom-fields.latest-stable.zip"
  ],
  "config": {
    "WP_DEBUG": true,
    "WP_DEBUG_LOG": true,
    "SCRIPT_DEBUG": true
  }
}
```

- [ ] **Step 4: Criar `.gitignore`**

Arquivo `themes/arena/.gitignore`:
```gitignore
/node_modules/
/vendor/
/assets/dist/
.wp-env.override.json
*.log
.DS_Store
```

- [ ] **Step 5: Criar `composer.json` (só ferramentas de dev)**

Arquivo `themes/arena/composer.json`:
```json
{
  "name": "arena/theme",
  "description": "Tema Arena - reconstrução limpa e moderna.",
  "license": "proprietary",
  "require": {
    "php": ">=8.1"
  },
  "require-dev": {
    "phpunit/phpunit": "^9",
    "yoast/phpunit-polyfills": "^2",
    "php-stubs/wordpress-stubs": "^6.4"
  },
  "config": {
    "optimize-autoloader": true
  }
}
```

- [ ] **Step 6: Inicializar git escopado à pasta do tema**

Run:
```bash
cd themes/arena && git init && git add -A && git commit -m "chore: bootstrap arena theme project (wp-env + tooling)"
```
Expected: repositório criado com o primeiro commit.

- [ ] **Step 7: Subir o ambiente**

Run:
```bash
cd themes/arena && wp-env start
```
Expected: WordPress disponível em `http://localhost:8888` (admin `admin`/`password`), tema "Arena" ainda não ativável (só existirá após Task 4).

---

### Task 2: Reconhecimento em produção (SSH somente leitura)

**Files:**
- Create: `docs/superpowers/recon/2026-07-24-pichauarena-findings.md`
- Create (local, fora do git do tema): `docs/superpowers/recon/prod-db.sql.gz` (temporário, não versionar)

**Interfaces:**
- Consumes: credenciais SSH (host `76.13.92.106`, porta `65002`).
- Produces: documento de achados com — (a) tema ativo confirmado; (b) se home/páginas usam `[bs-*]`; (c) lista de plugins ativos; (d) estrutura completa do menu/mega-menu; (e) dump do banco e amostra de uploads para importar local.

- [ ] **Step 1: Abrir sessão SSH read-only e confirmar WP-CLI**

Run:
```bash
ssh -p 65002 u651042240_M65iPVO8B@76.13.92.106 "cd ~/domains/*/public_html 2>/dev/null || cd ~/public_html; wp core version; wp theme list --status=active"
```
Expected: versão do WP e o tema ativo (esperado: `publisher`). **Nenhum comando de escrita.**

- [ ] **Step 2: Verificar se a home/páginas usam blocos `[bs-*]` (decide a ordem das fatias)**

Run:
```bash
ssh -p 65002 u651042240_M65iPVO8B@76.13.92.106 "cd ~/public_html; \
  echo '--- front page id ---'; wp option get page_on_front; wp option get show_on_front; \
  echo '--- bs-shortcodes em posts/páginas ---'; \
  wp db query \"SELECT COUNT(*) AS c FROM wp_posts WHERE post_status='publish' AND post_content LIKE '%[bs-%'\" --skip-column-names"
```
Expected: número de conteúdos que contêm `[bs-`. **Registrar no findings.** Se > 0 e incluir a home, a Fatia 2 (compatibilidade de blocos) precisa vir antes das templates do Plano 2.

- [ ] **Step 3: Exportar lista de plugins e menus**

Run:
```bash
ssh -p 65002 u651042240_M65iPVO8B@76.13.92.106 "cd ~/public_html; \
  wp plugin list --status=active --fields=name,version; \
  wp menu list; \
  for m in \$(wp menu list --field=slug); do echo \"== \$m ==\"; wp menu item list \$m --fields=type,title,url,menu_item_parent; done"
```
Expected: plugins ativos + itens de cada menu (para reproduzir o mega-menu no Plano 2).

- [ ] **Step 4: Exportar banco (leitura) e amostra de uploads**

Run:
```bash
ssh -p 65002 u651042240_M65iPVO8B@76.13.92.106 "cd ~/public_html; wp db export - --single-transaction 2>/dev/null | gzip" > docs/superpowers/recon/prod-db.sql.gz
```
Expected: `prod-db.sql.gz` baixado localmente. `wp db export` não altera o banco. (Uploads podem ser amostrados via SFTP conforme necessidade no Plano 2.)

- [ ] **Step 5: Escrever o documento de achados**

Preencher `docs/superpowers/recon/2026-07-24-pichauarena-findings.md` com: versão WP/PHP em produção, tema ativo, contagem/lista de conteúdos com `[bs-*]` (e se a home é um deles), plugins ativos com versão, estrutura completa dos menus, e observações de tokens visuais (cores/fontes vistas no CSS servido). **Este arquivo é a entrada do Plano 2.**

- [ ] **Step 6: Commit do documento de achados**

```bash
cd themes/arena  # (docs/ está fora do repo do tema; commitar no que fizer sentido para você)
# Se optar por versionar docs/ em um repo próprio de projeto, commitar lá. Caso contrário, manter local.
```
Expected: findings preservado (nunca commitar `prod-db.sql.gz`).

---

### Task 3: Importar conteúdo de produção no WP local

**Files:**
- Nenhum novo arquivo (operação no ambiente local).

**Interfaces:**
- Consumes: `docs/superpowers/recon/prod-db.sql.gz` (Task 2).
- Produces: WP local populado com o conteúdo real, para validação de paridade no Plano 2.

- [ ] **Step 1: Importar o dump no ambiente de testes local**

Run:
```bash
cd themes/arena
gunzip -c ../../docs/superpowers/recon/prod-db.sql.gz | wp-env run cli wp db import -
```
Expected: importação concluída.

- [ ] **Step 2: Corrigir URLs para o domínio local**

Run:
```bash
wp-env run cli wp search-replace 'https://www.pichauarena.com.br' 'http://localhost:8888' --all-tables --precise
```
Expected: contagem de substituições > 0.

- [ ] **Step 3: Smoke test**

Run:
```bash
wp-env run cli wp option get siteurl
```
Expected: `http://localhost:8888`. Abrir `http://localhost:8888` e confirmar que o site (ainda com Publisher) renderiza localmente.

---

## Fase B — Esqueleto do tema

### Task 4: Cabeçalho do tema, autoloader e bootstrap

**Files:**
- Create: `themes/arena/style.css`
- Create: `themes/arena/index.php`
- Create: `themes/arena/functions.php`
- Create: `themes/arena/inc/Theme.php`
- Create: `themes/arena/inc/Setup.php`
- Test: `themes/arena/tests/AutoloadTest.php`
- Create: `themes/arena/phpunit.xml.dist`
- Create: `themes/arena/tests/bootstrap.php`

**Interfaces:**
- Consumes: nada.
- Produces: `Arena\Theme::boot(): void`; autoloader que resolve `Arena\X\Y` → `inc/X/Y.php`; tema ativável.

- [ ] **Step 1: Escrever o teste do autoloader (falha)**

`themes/arena/tests/AutoloadTest.php`:
```php
<?php
declare(strict_types=1);

class AutoloadTest extends WP_UnitTestCase {
    public function test_theme_class_autoloads(): void {
        $this->assertTrue(class_exists('Arena\\Theme'), 'Arena\\Theme deveria autoloadar');
    }

    public function test_setup_class_autoloads(): void {
        $this->assertTrue(class_exists('Arena\\Setup'), 'Arena\\Setup deveria autoloadar');
    }
}
```

- [ ] **Step 2: Criar bootstrap e config do PHPUnit**

`themes/arena/tests/bootstrap.php`:
```php
<?php
declare(strict_types=1);

$_tests_dir = getenv('WP_TESTS_DIR') ?: '/wordpress-phpunit';
require $_tests_dir . '/includes/functions.php';

tests_add_filter('setup_theme', static function (): void {
    switch_theme('arena');
});

require dirname(__DIR__) . '/functions.php';
require $_tests_dir . '/includes/bootstrap.php';
```

`themes/arena/phpunit.xml.dist`:
```xml
<?xml version="1.0"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true">
    <testsuites>
        <testsuite name="arena">
            <directory suffix="Test.php">tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Rodar o teste (falha esperada)**

Run (Composer em container — não há PHP/Composer no host):
```bash
cd themes/arena && docker run --rm -v "$PWD":/app -w /app composer:2 install --ignore-platform-reqs
wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit
```
Expected: FAIL — `Arena\Theme` não existe (arquivos ainda não criados). Se o bootstrap dos testes não achar a WP test suite, definir `WP_TESTS_DIR` conforme o caminho exposto pelo `wp-env` (verificar com `wp-env run tests-cli env | grep -i test`) — reportar como BLOCKED se não resolver.

- [ ] **Step 4: Criar `style.css` (cabeçalho do tema)**

`themes/arena/style.css`:
```css
/*
Theme Name: Arena
Theme URI: https://www.pichauarena.com.br/
Author: Pichau Arena
Description: Tema moderno e performático (reconstrução limpa), foco em Core Web Vitals.
Version: 0.1.0
Requires at least: 6.4
Requires PHP: 8.1
Text Domain: arena
License: Proprietary
*/
```

- [ ] **Step 5: Criar o autoloader e o bootstrap em `functions.php`**

`themes/arena/functions.php`:
```php
<?php
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

define('ARENA_DIR', get_template_directory());
define('ARENA_URI', get_template_directory_uri());
define('ARENA_VERSION', '0.1.0');

// Autoloader PSR-4 próprio: Arena\Foo\Bar -> inc/Foo/Bar.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'Arena\\';
    if (!str_starts_with($class, $prefix)) { return; }
    $relative = substr($class, strlen($prefix));
    $path = ARENA_DIR . '/inc/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($path)) { require $path; }
});

Arena\Theme::boot();
```

- [ ] **Step 6: Criar `Arena\Theme` (orquestrador)**

`themes/arena/inc/Theme.php`:
```php
<?php
declare(strict_types=1);

namespace Arena;

final class Theme {
    /** Registra todos os módulos do tema. */
    public static function boot(): void {
        Setup::register();
    }
}
```

- [ ] **Step 7: Criar `Arena\Setup` (stub mínimo por ora)**

`themes/arena/inc/Setup.php`:
```php
<?php
declare(strict_types=1);

namespace Arena;

final class Setup {
    public static function register(): void {
        add_action('after_setup_theme', [self::class, 'themeSupports']);
    }

    public static function themeSupports(): void {
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
    }
}
```

- [ ] **Step 8: Criar `index.php` mínimo válido**

`themes/arena/index.php`:
```php
<?php
declare(strict_types=1);
get_header();
if (have_posts()) {
    while (have_posts()) { the_post(); the_title('<h2>', '</h2>'); }
}
get_footer();
```

- [ ] **Step 9: Rodar o teste (passa)**

Run:
```bash
wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit
```
Expected: PASS (2 testes).

- [ ] **Step 10: Commit**

```bash
git add -A && git commit -m "feat: theme skeleton with PSR-4 autoloader and boot"
```

---

### Task 5: Build Vite (CSS/JS modular + manifest)

**Files:**
- Create: `themes/arena/package.json`
- Create: `themes/arena/vite.config.js`
- Create: `themes/arena/assets/src/css/main.css`
- Create: `themes/arena/assets/src/js/main.js`

**Interfaces:**
- Consumes: nada.
- Produces: `assets/dist/manifest.json` mapeando `assets/src/js/main.js` e `assets/src/css/main.css` para arquivos com hash; scripts npm `dev`/`build`.

- [ ] **Step 1: Criar `package.json`**

`themes/arena/package.json`:
```json
{
  "name": "arena-theme",
  "private": true,
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vite build"
  },
  "devDependencies": {
    "vite": "^5.4.0"
  }
}
```

- [ ] **Step 2: Criar `vite.config.js` (gera manifest)**

`themes/arena/vite.config.js`:
```js
import { defineConfig } from 'vite';
import { resolve } from 'node:path';

export default defineConfig({
  base: '/wp-content/themes/arena/assets/dist/',
  build: {
    manifest: true,
    outDir: 'assets/dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        main: resolve(process.cwd(), 'assets/src/js/main.js'),
        style: resolve(process.cwd(), 'assets/src/css/main.css'),
      },
    },
  },
});
```

- [ ] **Step 3: Criar entradas CSS/JS mínimas**

`themes/arena/assets/src/css/main.css`:
```css
@layer base, components, utilities;

@layer base {
  :root {
    --arena-accent: #e00; /* placeholder; tokens reais vêm do Plano 2 via theme.json */
  }
  body { margin: 0; font-family: system-ui, sans-serif; }
}
```

`themes/arena/assets/src/js/main.js`:
```js
// Ponto de entrada JS do tema (ES6+, sem jQuery).
document.documentElement.classList.add('arena-ready');
```

- [ ] **Step 4: Buildar e verificar o manifest**

Run:
```bash
cd themes/arena && npm install && npm run build && cat assets/dist/.vite/manifest.json
```
Expected: `manifest.json` contendo chaves `assets/src/js/main.js` e `assets/src/css/main.css` com `file` apontando para arquivos hashados em `assets/dist/`.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "build: vite pipeline with manifest (modular CSS/JS)"
```

---

### Task 6: `Arena\Assets` — enfileirar via manifest

**Files:**
- Create: `themes/arena/inc/Assets.php`
- Modify: `themes/arena/inc/Theme.php`
- Test: `themes/arena/tests/AssetsTest.php`

**Interfaces:**
- Consumes: `assets/dist/.vite/manifest.json` (Task 5).
- Produces: `Arena\Assets::resolve(array $manifest, string $entry): ?array` (retorna `['file' => string, 'css' => string[]]` ou `null`); `Arena\Assets::register(): void` que engancha em `wp_enqueue_scripts`.

- [ ] **Step 1: Escrever o teste do resolvedor puro (falha)**

`themes/arena/tests/AssetsTest.php`:
```php
<?php
declare(strict_types=1);

use Arena\Assets;

class AssetsTest extends WP_UnitTestCase {
    private array $manifest = [
        'assets/src/js/main.js'  => ['file' => 'main-abc123.js', 'css' => ['main-def456.css']],
        'assets/src/css/main.css' => ['file' => 'main-def456.css'],
    ];

    public function test_resolve_returns_hashed_entry(): void {
        $r = Assets::resolve($this->manifest, 'assets/src/js/main.js');
        $this->assertSame('main-abc123.js', $r['file']);
        $this->assertSame(['main-def456.css'], $r['css']);
    }

    public function test_resolve_missing_entry_returns_null(): void {
        $this->assertNull(Assets::resolve($this->manifest, 'nope.js'));
    }
}
```

- [ ] **Step 2: Rodar o teste (falha esperada)**

Run:
```bash
wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit --filter AssetsTest
```
Expected: FAIL — `Arena\Assets` não existe.

- [ ] **Step 3: Implementar `Arena\Assets`**

`themes/arena/inc/Assets.php`:
```php
<?php
declare(strict_types=1);

namespace Arena;

final class Assets {
    private const MANIFEST = '/assets/dist/.vite/manifest.json';

    public static function register(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        add_filter('script_loader_tag', [self::class, 'deferOwnScripts'], 10, 2);
    }

    /** Resolvedor puro (testável): entrada -> dados do manifest. */
    public static function resolve(array $manifest, string $entry): ?array {
        if (!isset($manifest[$entry]['file'])) { return null; }
        return [
            'file' => $manifest[$entry]['file'],
            'css'  => $manifest[$entry]['css'] ?? [],
        ];
    }

    private static function manifest(): array {
        $path = ARENA_DIR . self::MANIFEST;
        if (!is_readable($path)) { return []; }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    public static function enqueue(): void {
        $manifest = self::manifest();
        $dist = ARENA_URI . '/assets/dist/';

        $js = self::resolve($manifest, 'assets/src/js/main.js');
        if ($js !== null) {
            foreach ($js['css'] as $css) {
                wp_enqueue_style('arena-main', $dist . $css, [], null);
            }
            wp_enqueue_script('arena-main', $dist . $js['file'], [], null, true);
        }
    }

    /** Aplica defer apenas aos scripts do próprio tema. */
    public static function deferOwnScripts(string $tag, string $handle): string {
        if (str_starts_with($handle, 'arena-')) {
            return str_replace(' src=', ' defer src=', $tag);
        }
        return $tag;
    }
}
```

- [ ] **Step 4: Registrar o módulo em `Theme::boot()`**

Em `themes/arena/inc/Theme.php`, dentro de `boot()`, após `Setup::register();`:
```php
        Assets::register();
```

- [ ] **Step 5: Rodar os testes (passam)**

Run:
```bash
wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit
```
Expected: PASS (todos).

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: asset enqueue via vite manifest with theme-scoped defer"
```

---

### Task 7: `Arena\Setup` completo (menus, sidebars, image sizes)

**Files:**
- Modify: `themes/arena/inc/Setup.php`
- Test: `themes/arena/tests/SetupTest.php`

**Interfaces:**
- Consumes: nada.
- Produces: menu location `arena_primary`; sidebar `arena-primary`; tamanho de imagem `arena-card` (760×428, crop).

- [ ] **Step 1: Escrever o teste (falha)**

`themes/arena/tests/SetupTest.php`:
```php
<?php
declare(strict_types=1);

class SetupTest extends WP_UnitTestCase {
    public function test_registers_primary_menu(): void {
        do_action('after_setup_theme');
        $this->assertArrayHasKey('arena_primary', get_registered_nav_menus());
    }

    public function test_registers_card_image_size(): void {
        do_action('after_setup_theme');
        $this->assertTrue(has_image_size('arena-card'));
    }
}
```

- [ ] **Step 2: Rodar (falha esperada)**

Run:
```bash
wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit --filter SetupTest
```
Expected: FAIL.

- [ ] **Step 3: Implementar**

Substituir o corpo de `themeSupports()` e adicionar registros em `themes/arena/inc/Setup.php`:
```php
    public static function register(): void {
        add_action('after_setup_theme', [self::class, 'themeSupports']);
        add_action('after_setup_theme', [self::class, 'menusAndSizes']);
        add_action('widgets_init', [self::class, 'sidebars']);
    }

    public static function menusAndSizes(): void {
        register_nav_menus(['arena_primary' => __('Menu Principal', 'arena')]);
        add_image_size('arena-card', 760, 428, true);
    }

    public static function sidebars(): void {
        register_sidebar([
            'id'            => 'arena-primary',
            'name'          => __('Sidebar Principal', 'arena'),
            'before_widget' => '<section id="%1$s" class="widget %2$s">',
            'after_widget'  => '</section>',
            'before_title'  => '<h3 class="widget__title">',
            'after_title'   => '</h3>',
        ]);
    }
```

- [ ] **Step 4: Rodar (passa)**

Run:
```bash
wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: register nav menu, sidebar and card image size"
```

---

## Fase C — Opções (ponte ACF)

### Task 8: `Arena\Options` — getters tipados com degradação segura

**Files:**
- Create: `themes/arena/inc/Options.php`
- Modify: `themes/arena/inc/Theme.php`
- Test: `themes/arena/tests/OptionsTest.php`

**Interfaces:**
- Consumes: função `get_field()` do ACF (pode não existir).
- Produces: `Arena\Options::get(string $key, mixed $default): mixed`; `Arena\Options::accentColor(): string`; `Arena\Options::logoId(): ?int`.

- [ ] **Step 1: Escrever o teste (falha)**

`themes/arena/tests/OptionsTest.php`:
```php
<?php
declare(strict_types=1);

use Arena\Options;

class OptionsTest extends WP_UnitTestCase {
    public function test_get_returns_default_when_acf_absent(): void {
        // ACF não está carregado no ambiente de teste -> deve cair no default.
        $this->assertSame('fallback', Options::get('qualquer_coisa', 'fallback'));
    }

    public function test_accent_color_has_sane_default(): void {
        $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{3,6}$/', Options::accentColor());
    }

    public function test_logo_id_null_by_default(): void {
        $this->assertNull(Options::logoId());
    }
}
```

- [ ] **Step 2: Rodar (falha esperada)**

Run:
```bash
wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit --filter OptionsTest
```
Expected: FAIL.

- [ ] **Step 3: Implementar**

`themes/arena/inc/Options.php`:
```php
<?php
declare(strict_types=1);

namespace Arena;

final class Options {
    /** Lê uma opção do ACF; degrada para $default se ACF ausente ou vazio. */
    public static function get(string $key, mixed $default = null): mixed {
        if (!function_exists('get_field')) { return $default; }
        $value = get_field($key, 'option');
        return ($value === null || $value === '' || $value === false) ? $default : $value;
    }

    public static function accentColor(): string {
        $c = self::get('arena_accent_color', '#e00000');
        return is_string($c) ? $c : '#e00000';
    }

    public static function logoId(): ?int {
        $v = self::get('arena_logo', null);
        return is_numeric($v) ? (int) $v : null;
    }
}
```

- [ ] **Step 4: Rodar (passa)**

Run:
```bash
wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: typed Options bridge over ACF with safe degradation"
```

---

### Task 9: Registro dos campos ACF (painel mínimo) via PHP

**Files:**
- Create: `themes/arena/inc/OptionsPanel.php`
- Modify: `themes/arena/inc/Theme.php`
- Test: `themes/arena/tests/OptionsPanelTest.php`

**Interfaces:**
- Consumes: `acf_add_local_field_group()`, `acf_add_options_page()` (podem não existir).
- Produces: `Arena\OptionsPanel::register(): void`; página de opções "Arena" com campos `arena_logo`, `arena_accent_color`, `arena_base_font`, `arena_sidebar_position`.

- [ ] **Step 1: Escrever o teste (falha)**

`themes/arena/tests/OptionsPanelTest.php`:
```php
<?php
declare(strict_types=1);

use Arena\OptionsPanel;

class OptionsPanelTest extends WP_UnitTestCase {
    public function test_field_definitions_shape(): void {
        $fields = OptionsPanel::fields();
        $keys = array_column($fields, 'name');
        $this->assertContains('arena_logo', $keys);
        $this->assertContains('arena_accent_color', $keys);
        $this->assertContains('arena_base_font', $keys);
        $this->assertContains('arena_sidebar_position', $keys);
    }

    public function test_register_is_noop_without_acf(): void {
        // Sem ACF carregado, register() não deve lançar erro.
        OptionsPanel::register();
        $this->assertTrue(true);
    }
}
```

- [ ] **Step 2: Rodar (falha esperada)**

Run:
```bash
wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit --filter OptionsPanelTest
```
Expected: FAIL.

- [ ] **Step 3: Implementar**

`themes/arena/inc/OptionsPanel.php`:
```php
<?php
declare(strict_types=1);

namespace Arena;

final class OptionsPanel {
    public static function register(): void {
        add_action('acf/init', [self::class, 'boot']);
    }

    public static function boot(): void {
        if (!function_exists('acf_add_options_page')) { return; }
        acf_add_options_page([
            'page_title' => 'Arena',
            'menu_title' => 'Arena',
            'menu_slug'  => 'arena-options',
            'capability' => 'edit_theme_options',
            'icon_url'   => 'dashicons-shield',
        ]);
        acf_add_local_field_group([
            'key'      => 'group_arena_options',
            'title'    => 'Opções do Arena',
            'fields'   => self::fields(),
            'location' => [[['param' => 'options_page', 'operator' => '==', 'value' => 'arena-options']]],
        ]);
    }

    /** Definição pura dos campos (testável). */
    public static function fields(): array {
        return [
            ['key' => 'field_arena_logo', 'name' => 'arena_logo', 'label' => 'Logo', 'type' => 'image', 'return_format' => 'id'],
            ['key' => 'field_arena_accent', 'name' => 'arena_accent_color', 'label' => 'Cor de destaque', 'type' => 'color_picker', 'default_value' => '#e00000'],
            ['key' => 'field_arena_font', 'name' => 'arena_base_font', 'label' => 'Fonte base', 'type' => 'text', 'default_value' => 'system-ui'],
            ['key' => 'field_arena_sidebar', 'name' => 'arena_sidebar_position', 'label' => 'Posição da sidebar', 'type' => 'select', 'choices' => ['right' => 'Direita', 'left' => 'Esquerda', 'none' => 'Sem sidebar'], 'default_value' => 'right'],
        ];
    }
}
```

- [ ] **Step 4: Registrar em `Theme::boot()`**

Em `themes/arena/inc/Theme.php`, dentro de `boot()`:
```php
        OptionsPanel::register();
```

- [ ] **Step 5: Rodar (passa)**

Run:
```bash
wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: minimal ACF options page registered via PHP"
```

---

## Fase D — Compatibilidade

### Task 10: `Arena\Compatibility` — plugins e higiene de performance

**Files:**
- Create: `themes/arena/inc/Compatibility.php`
- Modify: `themes/arena/inc/Theme.php`
- Test: `themes/arena/tests/CompatibilityTest.php`

**Interfaces:**
- Consumes: nada.
- Produces: `Arena\Compatibility::register(): void`; método puro `Arena\Compatibility::shouldLoadEmojis(): bool` (sempre false — remove o script de emojis do core).

- [ ] **Step 1: Escrever o teste (falha)**

`themes/arena/tests/CompatibilityTest.php`:
```php
<?php
declare(strict_types=1);

use Arena\Compatibility;

class CompatibilityTest extends WP_UnitTestCase {
    public function test_emojis_disabled(): void {
        $this->assertFalse(Compatibility::shouldLoadEmojis());
    }

    public function test_register_removes_emoji_action(): void {
        Compatibility::register();
        do_action('init');
        $this->assertFalse(has_action('wp_head', 'print_emoji_detection_script'));
    }
}
```

- [ ] **Step 2: Rodar (falha esperada)**

Run:
```bash
wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit --filter CompatibilityTest
```
Expected: FAIL.

- [ ] **Step 3: Implementar**

`themes/arena/inc/Compatibility.php`:
```php
<?php
declare(strict_types=1);

namespace Arena;

final class Compatibility {
    public static function shouldLoadEmojis(): bool {
        return false;
    }

    public static function register(): void {
        add_action('init', [self::class, 'trimCoreBloat']);
    }

    public static function trimCoreBloat(): void {
        if (!self::shouldLoadEmojis()) {
            remove_action('wp_head', 'print_emoji_detection_script', 7);
            remove_action('wp_print_styles', 'print_emoji_styles');
        }
        // RankMath, anúncios e cache: nenhuma ação necessária aqui — o tema
        // preserva wp_head()/wp_footer() e o loop padrão, então esses plugins
        // continuam funcionando. Ajustes específicos entram sob demanda.
    }
}
```

- [ ] **Step 4: Registrar em `Theme::boot()`**

Em `themes/arena/inc/Theme.php`, dentro de `boot()`:
```php
        Compatibility::register();
```

- [ ] **Step 5: Rodar (passa)**

Run:
```bash
wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: compatibility module trims core bloat, preserves plugin hooks"
```

---

## Fase E — Preview em produção

### Task 11: mu-plugin de preview por requisição

**Files:**
- Create: `mu-plugins/arena-preview.php` (destino final: `wp-content/mu-plugins/` da produção)
- Test: `themes/arena/tests/PreviewTest.php`
- Create: `themes/arena/inc/Preview.php`
- Modify: `themes/arena/inc/Theme.php`

**Interfaces:**
- Consumes: nada.
- Produces: `Arena\Preview::shouldPreview(bool $loggedInCan, ?string $tokenParam, ?string $expectedToken): bool` (lógica pura, testável); o mu-plugin usa essa lógica para forçar `template`/`stylesheet`.

- [ ] **Step 1: Escrever o teste da lógica pura (falha)**

`themes/arena/tests/PreviewTest.php`:
```php
<?php
declare(strict_types=1);

use Arena\Preview;

class PreviewTest extends WP_UnitTestCase {
    public function test_admin_capability_enables_preview(): void {
        $this->assertTrue(Preview::shouldPreview(true, null, 'segredo'));
    }

    public function test_matching_token_enables_preview(): void {
        $this->assertTrue(Preview::shouldPreview(false, 'segredo', 'segredo'));
    }

    public function test_wrong_token_denies(): void {
        $this->assertFalse(Preview::shouldPreview(false, 'errado', 'segredo'));
    }

    public function test_no_token_configured_denies_param_path(): void {
        $this->assertFalse(Preview::shouldPreview(false, 'qualquer', null));
    }
}
```

- [ ] **Step 2: Rodar (falha esperada)**

Run:
```bash
wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit --filter PreviewTest
```
Expected: FAIL.

- [ ] **Step 3: Implementar a lógica pura**

`themes/arena/inc/Preview.php`:
```php
<?php
declare(strict_types=1);

namespace Arena;

final class Preview {
    public const THEME = 'arena';

    /** Decide se a requisição deve visualizar o Arena. Lógica pura. */
    public static function shouldPreview(bool $loggedInCan, ?string $tokenParam, ?string $expectedToken): bool {
        if ($loggedInCan) { return true; }
        if ($expectedToken === null || $expectedToken === '') { return false; }
        return is_string($tokenParam) && hash_equals($expectedToken, $tokenParam);
    }
}
```

- [ ] **Step 4: Rodar (passa)**

Run:
```bash
wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit
```
Expected: PASS.

- [ ] **Step 5: Criar o mu-plugin que usa a lógica**

`mu-plugins/arena-preview.php`:
```php
<?php
declare(strict_types=1);
/*
Plugin Name: Arena Preview
Description: Força o tema "arena" apenas para admins logados ou requisições com token válido. Demais visitantes veem o tema ativo.
*/

if (!defined('ABSPATH')) { exit; }

add_action('setup_theme', static function (): void {
    // Autoload do tema não está disponível em mu-plugin: replicar a lógica pura mínima.
    $expected = defined('ARENA_PREVIEW_TOKEN') ? ARENA_PREVIEW_TOKEN : null;
    $loggedInCan = is_user_logged_in() && current_user_can('edit_theme_options');
    $param = isset($_GET['arena_preview']) ? (string) wp_unslash($_GET['arena_preview']) : null;

    $enable = $loggedInCan
        || ($expected !== null && $expected !== '' && is_string($param) && hash_equals($expected, $param));

    if (!$enable) { return; }

    add_filter('template', static fn (): string => 'arena');
    add_filter('stylesheet', static fn (): string => 'arena');
});
```

- [ ] **Step 6: Documentar o uso (cache) no README do tema**

Adicionar ao final de `themes/arena/README.md` (criar se não existir) a seção:
```markdown
## Preview em produção
1. Definir em `wp-config.php`: `define('ARENA_PREVIEW_TOKEN', '<token-secreto>');`
2. Copiar `mu-plugins/arena-preview.php` para `wp-content/mu-plugins/`.
3. Acessar como admin logado, ou usar `?arena_preview=<token-secreto>`.
4. **Cache:** garantir que o preview rode logado (LiteSpeed/WP Rocket ignoram logados) ou
   excluir o parâmetro `arena_preview` do cache. Sem isso, páginas cacheadas mostram o tema errado.
```

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat: per-request production preview (mu-plugin + tested pure logic)"
```

---

## Fase F — Tema filho

### Task 12: `arena-child`

**Files:**
- Create: `themes/arena-child/style.css`
- Create: `themes/arena-child/functions.php`

**Interfaces:**
- Consumes: tema pai `arena`.
- Produces: tema filho ativável que carrega o estilo do pai + o seu próprio.

- [ ] **Step 1: Criar `style.css` do filho**

`themes/arena-child/style.css`:
```css
/*
Theme Name: Arena Child
Template: arena
Author: Pichau Arena
Description: Tema filho do Arena para customizações seguras.
Version: 0.1.0
Text Domain: arena-child
*/
```

- [ ] **Step 2: Criar `functions.php` do filho**

`themes/arena-child/functions.php`:
```php
<?php
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'arena-child',
        get_stylesheet_directory_uri() . '/style.css',
        ['arena-main'],
        wp_get_theme()->get('Version')
    );
}, 20);
```

- [ ] **Step 3: Verificar ativação no wp-env**

Run:
```bash
wp-env run cli wp theme activate arena-child && wp-env run cli wp theme list --status=active
```
Expected: `arena-child` ativo, `arena` como parent. Abrir `http://localhost:8888` e confirmar que renderiza sem erro.

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "feat: arena-child theme scaffold"
```

---

## Self-Review

**1. Cobertura da spec (Fatia 1):**
- Estrutura de arquivos OOP + autoloader → Task 4 ✓
- Vite + manifest + CSS/JS modular → Tasks 5, 6 ✓
- Carregamento condicional/defer + higiene de performance → Tasks 6, 10 ✓ (LCP/CLS específicos de template ficam no Plano 2, pois dependem do markup real — documentado)
- Painel ACF (getters tipados + página de opções mínima) → Tasks 8, 9 ✓
- Preview em produção → Task 11 ✓
- Compatibilidade (hooks preservados, WPBakery ativo) → Task 10 ✓
- Tema filho → Task 12 ✓
- Ambiente local + testes → Tasks 1, 3 ✓
- Reconhecimento (resolve ordem das fatias) → Task 2 ✓
- **Gap consciente:** templates de paridade visual, tokens reais e mega-menu → **Plano 2** (dependem do recon).

**2. Placeholders:** o único literal `--arena-accent: #e00` está marcado como provisório e é substituído no Plano 2 via `theme.json`. Sem TODOs/TBDs pendentes.

**3. Consistência de tipos:** `Arena\Assets::resolve()` (array|null), `Arena\Options::get()`, `Arena\Preview::shouldPreview()` e `Arena\OptionsPanel::fields()` têm as mesmas assinaturas nos testes e nas implementações. `arena-main` é o handle usado no enqueue (Task 6) e como dependência no filho (Task 12) — consistente.

---

## Dependências e ordem

- Tasks 1→4→5→6 são a espinha dorsal (ambiente → esqueleto → build → enqueue).
- Task 2 (recon) pode rodar em paralelo assim que o SSH estiver disponível; **é pré-requisito do Plano 2**, não das Tasks 4–12.
- Tasks 7–12 dependem apenas do esqueleto (Task 4) e do enqueue (Task 6).
