<?php
declare(strict_types=1);

namespace Arena;

/**
 * Menu próprio "Arena" no admin, com abas — a tela de configuração que o tema
 * não tinha.
 *
 * Contexto de por que isto existe: o painel original era uma página de opções
 * do ACF (Arena\OptionsPanel). Só que `acf_add_options_page()` é recurso do ACF
 * **PRO**, e a produção tem a versão gratuita — então o menu nunca apareceu e o
 * dono do site ficou sem encontrar as opções de estilização. Esta classe não
 * depende de plugin nenhum: usa só APIs do próprio WordPress.
 *
 * Armazenamento: `theme_mod`, o mesmo do Customizer (Arena\Customizer registra
 * exatamente os mesmos ids a partir de Arena\Settings). As duas telas leem e
 * escrevem o mesmo valor — quem prefere ver o resultado ao vivo usa o
 * Customizer; quem prefere uma tela com todos os campos de uma vez usa esta.
 *
 * Segurança: capacidade `edit_theme_options` na exibição E na gravação, nonce
 * próprio por aba, e todo valor passa por Arena\Settings::sanitize() antes de
 * ser salvo.
 */
final class AdminPanel {
    public const MENU_SLUG = 'arena-painel';

    private const NONCE = 'arena_painel_salvar';

    public const CAPABILITY = 'edit_theme_options';

    public static function register(): void {
        add_action('admin_menu', [self::class, 'addMenu']);
        add_action('admin_post_arena_salvar_opcoes', [self::class, 'handleSave']);
    }

    public static function addMenu(): void {
        add_menu_page(
            __('Opções do Arena', 'arena'),
            __('Arena', 'arena'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [self::class, 'render'],
            'dashicons-shield-alt',
            59
        );
    }

    /** Aba ativa, validada contra os grupos existentes. */
    public static function currentGroup(mixed $solicitado = null): string {
        $solicitado = is_string($solicitado) ? $solicitado : '';
        return array_key_exists($solicitado, Settings::GROUPS)
            ? $solicitado
            : (string) array_key_first(Settings::GROUPS);
    }

    /**
     * Grava as opções da aba enviada.
     *
     * Só toca nos campos DAQUELA aba: assim salvar "Cores" nunca zera o que
     * está em "Layout" (campos ausentes do POST de uma aba não significam
     * "apagar", significam "não estavam nesta tela").
     *
     * @return array<string, string> ids salvos => valor gravado (retorno para os testes)
     */
    public static function saveGroup(string $grupo, array $enviado): array {
        $salvos = [];

        foreach (Settings::fieldsByGroup($grupo) as $id => $campo) {
            $bruto = $enviado[$id] ?? null;

            // Checkbox desmarcado não aparece no POST — aqui isso significa "0".
            if ($bruto === null && (string) ($campo['type'] ?? '') === 'checkbox') {
                $bruto = '0';
            }

            $valor = Settings::sanitize($id, $bruto);

            if ($valor === '') {
                remove_theme_mod($id); // volta ao padrão do tema
            } else {
                set_theme_mod($id, $valor);
            }

            $salvos[$id] = $valor;
        }

        return $salvos;
    }

    public static function handleSave(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Sem permissão para alterar as opções do tema.', 'arena'), '', ['response' => 403]);
        }

        check_admin_referer(self::NONCE);

        $grupo = self::currentGroup($_POST['arena_grupo'] ?? '');
        self::saveGroup($grupo, wp_unslash((array) $_POST));

        wp_safe_redirect(add_query_arg(
            ['page' => self::MENU_SLUG, 'aba' => $grupo, 'arena-salvo' => '1'],
            admin_url('admin.php')
        ));
        exit;
    }

    public static function render(): void {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        $grupo = self::currentGroup($_GET['aba'] ?? '');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Opções do Arena', 'arena'); ?></h1>

            <?php if (isset($_GET['arena-salvo'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Opções salvas.', 'arena'); ?></p>
                </div>
            <?php endif; ?>

            <p>
                <?php esc_html_e('Deixe um campo vazio para usar o padrão do tema. As mesmas opções estão em', 'arena'); ?>
                <a href="<?php echo esc_url(admin_url('customize.php')); ?>"><?php esc_html_e('Aparência → Personalizar → Arena', 'arena'); ?></a><?php esc_html_e(', onde você vê o resultado ao vivo antes de publicar. A logo do site fica em Personalizar → Identidade do site.', 'arena'); ?>
            </p>

            <h2 class="nav-tab-wrapper">
                <?php foreach (Settings::GROUPS as $chave => $titulo) : ?>
                    <a
                        href="<?php echo esc_url(add_query_arg(['page' => self::MENU_SLUG, 'aba' => $chave], admin_url('admin.php'))); ?>"
                        class="nav-tab <?php echo $chave === $grupo ? 'nav-tab-active' : ''; ?>"
                    ><?php echo esc_html($titulo); ?></a>
                <?php endforeach; ?>
            </h2>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="arena_salvar_opcoes">
                <input type="hidden" name="arena_grupo" value="<?php echo esc_attr($grupo); ?>">
                <?php wp_nonce_field(self::NONCE); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                    <?php foreach (Settings::fieldsByGroup($grupo) as $id => $campo) : ?>
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html((string) $campo['label']); ?></label>
                            </th>
                            <td><?php self::renderField($id, $campo); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php submit_button(__('Salvar opções', 'arena')); ?>
            </form>
        </div>
        <?php
    }

    /** @param array<string, mixed> $campo */
    private static function renderField(string $id, array $campo): void {
        $valor = Settings::value($id);
        $tipo = (string) ($campo['type'] ?? 'text');
        $descricao = (string) ($campo['description'] ?? '');
        $padrao = (string) ($campo['default'] ?? '');

        switch ($tipo) {
            case 'color':
                printf(
                    '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text" placeholder="%3$s" pattern="#?[0-9a-fA-F]{6}">',
                    esc_attr($id),
                    esc_attr($valor),
                    esc_attr($padrao !== '' ? $padrao : '#000000')
                );
                if ($valor !== '') {
                    printf(
                        '<span style="display:inline-block;width:24px;height:24px;vertical-align:middle;margin-left:8px;border:1px solid #ccd0d4;background:%s"></span>',
                        esc_attr($valor)
                    );
                }
                break;

            case 'select':
                printf('<select id="%1$s" name="%1$s">', esc_attr($id));
                foreach ((array) ($campo['choices'] ?? []) as $chave => $rotulo) {
                    printf(
                        '<option value="%s"%s>%s</option>',
                        esc_attr((string) $chave),
                        selected((string) $chave, $valor, false),
                        esc_html((string) $rotulo)
                    );
                }
                echo '</select>';
                break;

            case 'number':
                printf(
                    '<input type="number" id="%1$s" name="%1$s" value="%2$s" class="small-text"%3$s%4$s%5$s>',
                    esc_attr($id),
                    esc_attr($valor),
                    isset($campo['min']) ? ' min="' . esc_attr((string) $campo['min']) . '"' : '',
                    isset($campo['max']) ? ' max="' . esc_attr((string) $campo['max']) . '"' : '',
                    isset($campo['step']) ? ' step="' . esc_attr((string) $campo['step']) . '"' : ''
                );
                if (isset($campo['unit'])) {
                    echo ' ' . esc_html((string) $campo['unit']);
                }
                break;

            case 'checkbox':
                printf(
                    '<label><input type="checkbox" id="%1$s" name="%1$s" value="1"%2$s> %3$s</label>',
                    esc_attr($id),
                    checked($valor === '' ? ($padrao === '1') : $valor === '1', true, false),
                    esc_html__('Ativado', 'arena')
                );
                break;

            case 'image':
                printf(
                    '<input type="number" id="%1$s" name="%1$s" value="%2$s" class="small-text" min="1">',
                    esc_attr($id),
                    esc_attr($valor)
                );
                $anexo = $valor !== '' ? wp_get_attachment_image((int) $valor, [80, 80]) : '';
                if ($anexo !== '') {
                    echo '<div style="margin-top:8px">' . $anexo . '</div>';
                }
                break;

            default:
                printf(
                    '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text">',
                    esc_attr($id),
                    esc_attr($valor)
                );
        }

        if ($descricao !== '') {
            printf('<p class="description">%s</p>', esc_html($descricao));
        }
    }
}
