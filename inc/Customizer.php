<?php
declare(strict_types=1);

namespace Arena;

/**
 * task-native-settings: the theme's PRIMARY configuration surface, working
 * with ZERO plugins — the ACF options page (Arena\OptionsPanel) previously
 * was the ONLY place to set these, but ACF is not installed on production,
 * so the site owner had no configuration UI at all ("não achei onde altero
 * configurações do tema, como adicionar a logo").
 *
 * The logo itself is handled separately, via WordPress' own native
 * `custom-logo` theme support (Arena\Setup::themeSupports() +
 * template-parts/header/branding.php) — WordPress registers that
 * Customizer control (under the built-in "Identidade do site" section)
 * automatically once `add_theme_support('custom-logo', …)` is declared, so
 * this class does not add its own logo control.
 *
 * Every setting registered here is a `theme_mod` (not an `option`) with
 * `transport => 'refresh'` (none of these values are wired to the
 * postMessage live-preview JS, so a full refresh is what actually reflects
 * the change) and is read back by Arena\Options — which resolves
 * theme_mod → ACF (if active) → hard default, see inc/Options.php's own
 * docblock for the full chain. That's what guarantees the accessible-accent
 * derivation (`Options::accessibleTextColor()`) applies identically here as
 * it does to an ACF-set accent: both paths converge on the same
 * `Options::cssTokens()` before anything is ever printed.
 */
final class Customizer {
    public const PANEL_ID = 'arena';

    public const SECTION_ID = 'arena_settings';

    public static function register(): void {
        add_action('customize_register', [self::class, 'boot']);
    }

    public static function boot(\WP_Customize_Manager $wp_customize): void {
        $wp_customize->add_panel(self::PANEL_ID, [
            'title'       => __('Arena', 'arena'),
            'description' => __(
                'Configurações do tema Arena. A logo do site fica em "Identidade do site", ao lado — as opções abaixo são as demais configurações que antes só existiam num painel do ACF (agora opcional).',
                'arena'
            ),
            'priority'    => 160,
        ]);

        $wp_customize->add_section(self::SECTION_ID, [
            'title'       => __('Opções do Arena', 'arena'),
            'description' => __(
                'Cor de destaque, posição da sidebar e fonte base do tema. Cada opção aqui tem prioridade sobre o painel de opções do ACF (se o plugin estiver ativo) — e sobre o valor padrão do tema, se nada for definido em nenhum dos dois.',
                'arena'
            ),
            'panel'       => self::PANEL_ID,
            'priority'    => 10,
        ]);

        self::registerAccentColor($wp_customize);
        self::registerSidebarPosition($wp_customize);
        self::registerBaseFont($wp_customize);
        self::registerSchemaSections($wp_customize);
    }

    /**
     * Seções geradas a partir de Arena\Settings — cores do cabeçalho/rodapé,
     * tipografia, layout e blocos. Ficam aqui além do menu próprio
     * (Arena\AdminPanel) porque no Customizer o dono do site vê o efeito ao
     * vivo antes de publicar, o que para cor e tipografia faz toda a
     * diferença. As duas telas escrevem no MESMO `theme_mod`, então não há
     * sincronização a fazer nem valor divergente possível.
     *
     * `arena_sidebar_position` é pulado: já é registrado acima, na seção
     * original, e registrar duas vezes o mesmo id criaria dois controles para
     * o mesmo valor.
     */
    private static function registerSchemaSections(\WP_Customize_Manager $wp_customize): void {
        $prioridade = 20;

        foreach (Settings::GROUPS as $grupo => $titulo) {
            $campos = Settings::fieldsByGroup($grupo);
            if ($campos === []) {
                continue;
            }

            $secao = 'arena_grupo_' . $grupo;
            $wp_customize->add_section($secao, [
                'title'       => $titulo,
                'description' => __('Deixe um campo vazio para manter o padrão do tema.', 'arena'),
                'panel'       => self::PANEL_ID,
                'priority'    => $prioridade,
            ]);
            $prioridade += 10;

            foreach ($campos as $id => $campo) {
                if ($wp_customize->get_setting($id) !== null) {
                    continue; // já registrado na seção original
                }

                $wp_customize->add_setting($id, [
                    'type'              => 'theme_mod',
                    'default'           => '',
                    'sanitize_callback' => static fn (mixed $valor): string => Settings::sanitize($id, $valor),
                    'transport'         => 'refresh',
                ]);

                self::addSchemaControl($wp_customize, $secao, $id, $campo);
            }
        }
    }

    /** @param array<string, mixed> $campo */
    private static function addSchemaControl(
        \WP_Customize_Manager $wp_customize,
        string $secao,
        string $id,
        array $campo
    ): void {
        $base = [
            'section'     => $secao,
            'label'       => (string) ($campo['label'] ?? $id),
            'description' => (string) ($campo['description'] ?? ''),
        ];

        switch ((string) ($campo['type'] ?? 'text')) {
            case 'color':
                $wp_customize->add_control(new \WP_Customize_Color_Control($wp_customize, $id, $base + ['settings' => $id]));
                return;

            case 'image':
                $wp_customize->add_control(new \WP_Customize_Media_Control($wp_customize, $id, $base + [
                    'settings'  => $id,
                    'mime_type' => 'image',
                ]));
                return;

            case 'select':
                $wp_customize->add_control($id, $base + [
                    'type'    => 'select',
                    'choices' => (array) ($campo['choices'] ?? []),
                ]);
                return;

            case 'number':
                $wp_customize->add_control($id, $base + [
                    'type'        => 'number',
                    'input_attrs' => array_filter([
                        'min'  => $campo['min'] ?? null,
                        'max'  => $campo['max'] ?? null,
                        'step' => $campo['step'] ?? null,
                    ], static fn ($v): bool => $v !== null),
                ]);
                return;

            case 'checkbox':
                $wp_customize->add_control($id, $base + ['type' => 'checkbox']);
                return;

            default:
                $wp_customize->add_control($id, $base + ['type' => 'text']);
        }
    }

    private static function registerAccentColor(\WP_Customize_Manager $wp_customize): void {
        $wp_customize->add_setting('arena_accent_color', [
            'type'              => 'theme_mod',
            'default'           => Options::DEFAULT_ACCENT,
            'sanitize_callback' => [self::class, 'sanitizeAccentColor'],
            'transport'         => 'refresh',
        ]);

        $wp_customize->add_control(new \WP_Customize_Color_Control(
            $wp_customize,
            'arena_accent_color',
            [
                'label'       => __('Cor de destaque', 'arena'),
                'description' => __(
                    'Usada em botões, links e badges. Uma variante escura e acessível (contraste mínimo 4.6:1 contra branco) é calculada automaticamente para qualquer cor escolhida — nunca precisa escolher uma cor "seguro".',
                    'arena'
                ),
                'section'     => self::SECTION_ID,
                'priority'    => 10,
            ]
        ));
    }

    private static function registerSidebarPosition(\WP_Customize_Manager $wp_customize): void {
        $wp_customize->add_setting('arena_sidebar_position', [
            'type'              => 'theme_mod',
            'default'           => Options::DEFAULT_SIDEBAR_POSITION,
            'sanitize_callback' => [self::class, 'sanitizeSidebarPosition'],
            'transport'         => 'refresh',
        ]);

        $wp_customize->add_control('arena_sidebar_position', [
            'label'       => __('Posição da sidebar', 'arena'),
            'description' => __(
                'Onde a coluna lateral aparece em matérias, páginas, categorias e busca. "Sem sidebar" expande o conteúdo para a largura total.',
                'arena'
            ),
            'section'     => self::SECTION_ID,
            'type'        => 'select',
            'choices'     => [
                'right' => __('Direita', 'arena'),
                'left'  => __('Esquerda', 'arena'),
                'none'  => __('Sem sidebar', 'arena'),
            ],
            'priority'    => 20,
        ]);
    }

    private static function registerBaseFont(\WP_Customize_Manager $wp_customize): void {
        $wp_customize->add_setting('arena_base_font', [
            'type'              => 'theme_mod',
            'default'           => Options::DEFAULT_BASE_FONT,
            'sanitize_callback' => [self::class, 'sanitizeBaseFont'],
            'transport'         => 'refresh',
        ]);

        $wp_customize->add_control('arena_base_font', [
            'label'       => __('Fonte base', 'arena'),
            'description' => __(
                'O par Barlow + Oswald é o padrão visual do tema (self-hosted, sem chamadas externas). "Fontes do sistema" usa a pilha de fontes nativa do sistema operacional do visitante — zero rede adicional.',
                'arena'
            ),
            'section'     => self::SECTION_ID,
            'type'        => 'select',
            'choices'     => [
                'barlow-oswald' => __('Barlow + Oswald (padrão do tema)', 'arena'),
                'system'        => __('Fontes do sistema', 'arena'),
            ],
            'priority'    => 30,
        ]);
    }

    /**
     * Pure sanitiser (testable): `sanitize_hex_color()` already returns
     * `null` for anything that isn't a well-formed `#rgb`/`#rrggbb` hex
     * string — but a `null` theme_mod value reads back from
     * `get_theme_mod()` indistinguishably from "never set", which is
     * harmless for Arena\Options::accentColor() (its own theme_mod check
     * requires a valid hex string, so `null` just falls through to the next
     * step in the chain) but would silently discard whatever the previous
     * valid value was. Falling back to the theme's own default here instead
     * means an owner who fat-fingers the colour-picker's text input still
     * gets a deterministic, valid accent rather than a "reverts to
     * whatever ACF/hard-default resolves to" surprise.
     */
    public static function sanitizeAccentColor(mixed $value): string {
        if (!is_string($value)) {
            return Options::DEFAULT_ACCENT;
        }
        $sanitized = sanitize_hex_color($value);
        return $sanitized !== null && $sanitized !== '' ? $sanitized : Options::DEFAULT_ACCENT;
    }

    /** Pure sanitiser (testable): whitelist against Options::SIDEBAR_POSITIONS. */
    public static function sanitizeSidebarPosition(mixed $value): string {
        return is_string($value) && in_array($value, Options::SIDEBAR_POSITIONS, true)
            ? $value
            : Options::DEFAULT_SIDEBAR_POSITION;
    }

    /** Pure sanitiser (testable): whitelist against Options::FONT_STACKS's keys. */
    public static function sanitizeBaseFont(mixed $value): string {
        return is_string($value) && isset(Options::FONT_STACKS[$value])
            ? $value
            : Options::DEFAULT_BASE_FONT;
    }
}
