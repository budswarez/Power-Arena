<?php
declare(strict_types=1);

use Arena\Compatibility;

class CompatibilityTest extends WP_UnitTestCase {
    public function test_emojis_disabled(): void {
        $this->assertFalse(Compatibility::shouldLoadEmojis());
    }

    /**
     * register() must wire trimCoreBloat() to run on 'init'. We assert the
     * hook is attached without firing 'init' — see
     * test_trim_core_bloat_removes_emoji_action() for why re-firing core
     * hooks inside a test is avoided.
     */
    public function test_register_hooks_trim_core_bloat_to_init(): void {
        Compatibility::register();

        $this->assertNotFalse(has_action('init', [Compatibility::class, 'trimCoreBloat']));
    }

    /**
     * Exercises trimCoreBloat() directly instead of firing 'init'.
     *
     * Re-firing 'init' inside a test also re-runs WordPress core's own
     * init-time registrations (block types, block bindings sources, the
     * font library's default collection), which are not idempotent and can
     * emit `_doing_it_wrong()` notices on a second call — WordPress-version-
     * dependent, unrelated to Compatibility. Calling trimCoreBloat() and
     * checking has_action() directly keeps this test deterministic and
     * scoped to Compatibility's own behavior, independent of what the
     * installed core version does when 'init' fires twice.
     */
    public function test_trim_core_bloat_removes_emoji_action(): void {
        // Establish the precondition core normally sets up at boot,
        // regardless of what the current install already did.
        add_action('wp_head', 'print_emoji_detection_script', 7);
        add_action('wp_print_styles', 'print_emoji_styles');
        $this->assertNotFalse(has_action('wp_head', 'print_emoji_detection_script'));
        $this->assertNotFalse(has_action('wp_print_styles', 'print_emoji_styles'));

        Compatibility::trimCoreBloat();

        $this->assertFalse(has_action('wp_head', 'print_emoji_detection_script'));
        $this->assertFalse(has_action('wp_print_styles', 'print_emoji_styles'));
    }

    /**
     * Bug real de produção: o wpDiscuz registra o script de bloco dele com
     * `wp-edit-post` na lista de dependências e o enfileira em TODOS os
     * editores de blocos. Na tela de widgets isso carrega o `editor.min.js`
     * junto do `edit-widgets.min.js`; no WP 7.0 os dois embutem a store
     * `core/interface`, o segundo registro lança e salvar widget passa a
     * falhar com "Cannot read properties of undefined (reading '0')".
     */
    public function test_dequeues_plugin_scripts_that_pull_the_post_editor_into_the_widgets_screen(): void {
        wp_register_script(
            'fake-plugin-block',
            'https://example.org/wp-content/plugins/fake/build/index.js',
            ['wp-edit-post'],
            '1.0',
            true
        );
        wp_enqueue_script('fake-plugin-block');
        $this->assertTrue(wp_script_is('fake-plugin-block', 'enqueued'));

        $removidos = Compatibility::keepPostEditorOutOfWidgetScreens('widgets.php');

        $this->assertContains('fake-plugin-block', $removidos);
        $this->assertFalse(wp_script_is('fake-plugin-block', 'enqueued'));
    }

    /** A dependência proibida costuma estar a mais de um nível de distância. */
    public function test_detects_the_forbidden_package_through_a_transitive_dependency(): void {
        wp_register_script('fake-mid', 'https://example.org/wp-content/plugins/fake/mid.js', ['wp-edit-post'], '1.0', true);
        wp_register_script('fake-leaf', 'https://example.org/wp-content/plugins/fake/leaf.js', ['fake-mid'], '1.0', true);
        wp_enqueue_script('fake-leaf');

        $removidos = Compatibility::keepPostEditorOutOfWidgetScreens('widgets.php');

        $this->assertContains('fake-leaf', $removidos);
    }

    /** Fora das telas de widgets, nada é removido — o editor de posts precisa deles. */
    public function test_leaves_the_post_editor_alone_on_other_admin_screens(): void {
        wp_register_script('fake-plugin-block', 'https://example.org/wp-content/plugins/fake/build/index.js', ['wp-edit-post'], '1.0', true);
        wp_enqueue_script('fake-plugin-block');

        $this->assertSame([], Compatibility::keepPostEditorOutOfWidgetScreens('post.php'));
        $this->assertTrue(wp_script_is('fake-plugin-block', 'enqueued'));
    }

    /**
     * Trava de segurança: handles do CORE nunca são removidos. Sem isso, uma
     * versão futura em que `wp-edit-widgets` dependesse de `wp-editor` faria
     * este método derrubar o próprio editor de widgets.
     */
    public function test_never_dequeues_core_handles(): void {
        wp_enqueue_script('wp-edit-widgets');
        wp_enqueue_script('wp-editor');

        $removidos = Compatibility::keepPostEditorOutOfWidgetScreens('widgets.php');

        $this->assertNotContains('wp-edit-widgets', $removidos);
        $this->assertNotContains('wp-editor', $removidos);
        $this->assertTrue(wp_script_is('wp-edit-widgets', 'enqueued'));
    }

    public function test_register_hooks_the_widget_screen_guard(): void {
        Compatibility::register();

        $this->assertNotFalse(
            has_action('admin_enqueue_scripts', [Compatibility::class, 'keepPostEditorOutOfWidgetScreens'])
        );
        $this->assertNotFalse(
            has_filter('use_widgets_block_editor', [Compatibility::class, 'classicWidgetsScreenWhenBatchRouteMissing'])
        );
    }

    /** Remove a rota /batch/v1 como a hospedagem faz, para os testes abaixo. */
    private function removeBatchRoute(): void {
        add_filter('rest_endpoints', static function ($endpoints) {
            unset($endpoints['/batch/v1']);
            return $endpoints;
        }, PHP_INT_MAX);
    }

    /**
     * Sem a rota `/batch/v1`, o editor de widgets em blocos não consegue salvar
     * (ver o comentário do método). Nesse cenário a tela precisa cair para a
     * interface clássica, que salva por POST.
     */
    public function test_falls_back_to_the_classic_widgets_screen_when_the_batch_route_is_missing(): void {
        $this->removeBatchRoute();
        $GLOBALS['pagenow'] = 'widgets.php';

        $this->assertFalse(Compatibility::classicWidgetsScreenWhenBatchRouteMissing(true));
    }

    /** Com a rota presente, o editor moderno volta sozinho — nada a desfazer. */
    public function test_keeps_the_block_editor_when_the_batch_route_exists(): void {
        $GLOBALS['pagenow'] = 'widgets.php';
        $this->assertArrayHasKey('/batch/v1', rest_get_server()->get_routes(), 'pré-condição: rota presente');

        $this->assertTrue(Compatibility::classicWidgetsScreenWhenBatchRouteMissing(true));
    }

    /**
     * O painel de widgets do Customizer usa `wp-customize-widgets`, que não
     * passa por `/batch/v1` e funciona — não deve perder o editor em blocos.
     */
    public function test_does_not_touch_the_customizer_widgets_panel(): void {
        $this->removeBatchRoute();
        $GLOBALS['pagenow'] = 'customize.php';

        $this->assertTrue(Compatibility::classicWidgetsScreenWhenBatchRouteMissing(true));
    }

    /** Fora do admin de widgets, o valor recebido passa intacto. */
    public function test_passes_the_incoming_value_through_on_other_screens(): void {
        $this->removeBatchRoute();
        $GLOBALS['pagenow'] = 'post.php';

        $this->assertTrue(Compatibility::classicWidgetsScreenWhenBatchRouteMissing(true));
        $this->assertFalse(Compatibility::classicWidgetsScreenWhenBatchRouteMissing(false));
    }
}
