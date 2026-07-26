<?php
declare(strict_types=1);

namespace Arena;

final class Compatibility {
    public static function shouldLoadEmojis(): bool {
        return false;
    }

    public static function register(): void {
        add_action('init', [self::class, 'trimCoreBloat']);
        // PHP_INT_MAX: só depois de TODOS os plugins terem enfileirado o que
        // querem, senão não há o que remover.
        add_action('admin_enqueue_scripts', [self::class, 'keepPostEditorOutOfWidgetScreens'], PHP_INT_MAX);
        add_filter('use_widgets_block_editor', [self::class, 'classicWidgetsScreenWhenBatchRouteMissing']);
    }

    /**
     * Volta a tela `Aparência → Widgets` para a interface clássica QUANDO — e
     * somente quando — a rota REST `/batch/v1` não existe.
     *
     * Por que: o editor de widgets em blocos salva através dessa rota. Antes de
     * enviar os dados ele faz `OPTIONS /batch/v1` e lê
     * `resposta.endpoints[0].args.requests.maxItems`
     * (wp-includes/js/dist/core-data.js, defaultProcessor). Se a rota não está
     * registrada, `rest_handle_options_request()` responde 200 com corpo `[]`,
     * `endpoints` fica indefinido e o JS quebra em `undefined[0]` — o usuário vê
     * "Ocorreu um erro. Cannot read properties of undefined (reading '0')" e
     * NADA é salvo (a requisição de gravação nunca sai do navegador).
     *
     * Medido em produção (pichauarena, hospedagem Hostinger/h5g): o mu-plugin da
     * plataforma, em `/opt/h5g/usr/mu-plugins/hostinger-h5g-plugin.php`, faz
     * `unset($endpoints['/batch/v1'])` no filtro `rest_endpoints` com prioridade
     * PHP_INT_MAX, comentado como "Temporary block /batch/v1 for security
     * reasons". O arquivo é root:root — o dono do site não pode alterá-lo. O
     * mesmo acontece num segundo site da mesma conta, com outro tema, o que
     * confirma ser da plataforma e não do tema.
     *
     * A interface clássica salva por POST de formulário, sem tocar na REST API,
     * então volta a funcionar. Escopo deliberadamente estreito:
     *
     * - Só em `widgets.php`. O painel de widgets do Customizer usa outro editor
     *   (`wp-customize-widgets`) que NÃO passa por `/batch/v1` e funciona
     *   normalmente — desligá-lo ali só tiraria a interface melhor de quem já
     *   consegue usá-la.
     * - Só quando a rota está realmente ausente. Assim que a hospedagem
     *   remover o bloqueio, o editor em blocos volta sozinho, sem ninguém
     *   precisar lembrar de desfazer nada aqui.
     *
     * @param bool $usar valor vindo de wp_use_widgets_block_editor()
     */
    public static function classicWidgetsScreenWhenBatchRouteMissing(mixed $usar): bool {
        if (($GLOBALS['pagenow'] ?? '') !== 'widgets.php') {
            return (bool) $usar;
        }

        return self::batchRouteIsAvailable() ? (bool) $usar : false;
    }

    /**
     * A rota `/batch/v1` está registrada nesta instalação?
     *
     * `get_routes()` reaplica o filtro `rest_endpoints` a cada chamada, que é
     * exatamente o que queremos: a resposta reflete o estado real, inclusive
     * remoções feitas por mu-plugins da hospedagem.
     */
    private static function batchRouteIsAvailable(): bool {
        if (!function_exists('rest_get_server')) {
            return true; // sem REST disponível, não há o que decidir aqui
        }

        return array_key_exists('/batch/v1', rest_get_server()->get_routes());
    }

    /**
     * Telas do editor de widgets onde os pacotes do editor de POSTS não podem
     * entrar (é o próprio WordPress que proíbe — ver wp_check_widget_editor_deps()).
     */
    private const WIDGET_SCREENS = ['widgets.php', 'customize.php'];

    /** Pacotes que, presentes numa tela de widgets, quebram o editor. */
    private const FORBIDDEN = ['wp-editor', 'wp-edit-post'];

    /**
     * Impede que scripts de PLUGIN arrastem os pacotes do editor de posts para
     * a tela de widgets.
     *
     * Medido em produção (pichauarena, WP 7.0.2): o editor de widgets ficava
     * inutilizável — ao salvar, aparecia "Ocorreu um erro. Cannot read
     * properties of undefined (reading '0')", e o console mostrava
     * `Store "core/interface" is already registered` disparado pelo
     * `edit-widgets.min.js`. Causa: o wpDiscuz registra o script de bloco dele
     * (`wpdiscuz-inline-feedback-button-js`) declarando `wp-edit-post` como
     * dependência, e o registra para TODOS os editores de blocos. A cadeia
     * `wpdiscuz-inline-feedback-button-js -> wp-edit-post -> wp-editor` fazia o
     * `editor.min.js` ser carregado junto do `edit-widgets.min.js`, e no WP 7.0
     * ambos embutem a store `core/interface` (o handle `wp-interface` deixou de
     * existir) — a segunda tentativa de registro lança, a inicialização do
     * editor aborta no meio e o salvamento morre lendo `[0]` de algo que nunca
     * foi construído.
     *
     * Não é bug do tema: reproduzia igual com o tema anterior (Publisher).
     * O certo seria um mu-plugin, mas a pasta `mu-plugins` é do root em
     * hospedagens gerenciadas (Hostinger), então o tema é o único lugar
     * gravável. Quando o plugin corrigir a dependência, este método pode sair.
     *
     * Segurança da remoção: só mexe em handles de PLUGIN/TEMA (`src` dentro de
     * wp-content). Handles do core nunca são removidos — se um deles puxasse
     * `wp-editor` ali, seria problema do core resolver, e remover às cegas
     * poderia derrubar o próprio `wp-edit-widgets`.
     *
     * @return list<string> handles removidos (o retorno existe para os testes).
     */
    public static function keepPostEditorOutOfWidgetScreens(string $hookSuffix = ''): array {
        if (!in_array($hookSuffix, self::WIDGET_SCREENS, true)) {
            return [];
        }

        $scripts = wp_scripts();
        $removidos = [];

        foreach ($scripts->queue as $handle) {
            $handle = (string) $handle;
            $registrado = $scripts->registered[$handle] ?? null;
            if ($registrado === null) {
                continue;
            }

            // Apenas scripts vindos de wp-content (plugins/temas).
            if (strpos((string) $registrado->src, '/wp-content/') === false) {
                continue;
            }

            if (self::reachesForbiddenPackage($scripts, $handle)) {
                wp_dequeue_script($handle);
                $removidos[] = $handle;
            }
        }

        return $removidos;
    }

    /**
     * O handle alcança algum pacote proibido pela árvore de dependências?
     *
     * A busca é recursiva de propósito: no caso real o pacote estava a DOIS
     * níveis de distância, e uma checagem só das dependências diretas não
     * encontrava nada (foi o meu primeiro diagnóstico, errado).
     *
     * @param array<string, true> $visitados
     */
    private static function reachesForbiddenPackage(\WP_Scripts $scripts, string $handle, array $visitados = []): bool {
        if (in_array($handle, self::FORBIDDEN, true)) {
            return true;
        }

        if (isset($visitados[$handle])) {
            return false; // ciclo entre dependências
        }
        $visitados[$handle] = true;

        $registrado = $scripts->registered[$handle] ?? null;
        if ($registrado === null) {
            return false;
        }

        foreach ((array) $registrado->deps as $dep) {
            if (self::reachesForbiddenPackage($scripts, (string) $dep, $visitados)) {
                return true;
            }
        }

        return false;
    }

    public static function trimCoreBloat(): void {
        if (!self::shouldLoadEmojis()) {
            remove_action('wp_head', 'print_emoji_detection_script', 7);
            // Explicit priority 10 (WP core's own default for this action)
            // for symmetry with the `wp_head` removal above, which must
            // name its non-default priority 7 explicitly to match.
            remove_action('wp_print_styles', 'print_emoji_styles', 10);
        }
        // RankMath, anúncios e cache: nenhuma ação necessária aqui — o tema
        // preserva wp_head()/wp_footer() e o loop padrão, então esses plugins
        // continuam funcionando. Ajustes específicos entram sob demanda.
    }
}
