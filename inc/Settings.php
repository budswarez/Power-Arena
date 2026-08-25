<?php
declare(strict_types=1);

namespace Arena;

/**
 * Esquema único das opções de estilização do tema.
 *
 * Por que existe: até aqui o tema tinha três controles (cor de destaque,
 * fonte, sidebar) e o painel do ACF — que nunca apareceu na produção, porque
 * páginas de opções são recurso do ACF PRO e o site tem a versão gratuita. Esta
 * classe é a fonte única da verdade das opções, consumida por TRÊS lugares sem
 * duplicar definição:
 *
 *   - Arena\AdminPanel   → menu próprio "Arena" no admin (sem plugin nenhum)
 *   - Arena\Customizer   → mesmas opções com pré-visualização ao vivo
 *   - Arena\Options      → resolve o valor e emite as variáveis CSS
 *
 * Decisão de projeto importante: o valor VAZIO significa "usar o padrão do
 * tema", e nesse caso a variável CSS **não é emitida** — o `var(--token,
 * valor-atual)` no CSS mantém exatamente a aparência de hoje. Isso garante que
 * publicar esta versão num site em produção não muda um pixel até alguém
 * escolher algo. Os `default` abaixo são só o que o campo mostra como
 * referência, não valores forçados.
 *
 * Armazenamento: `theme_mod` (o mesmo do Customizer), então as duas telas leem
 * e escrevem o mesmo lugar e não há sincronização a fazer.
 */
final class Settings {
    /** Abas/seções, na ordem em que aparecem. */
    public const GROUPS = [
        'cores'      => 'Cores',
        'tipografia' => 'Tipografia',
        'layout'     => 'Layout e largura',
        'blocos'     => 'Blocos e listagens',
    ];

    /**
     * Definição pura de todos os campos.
     *
     * `css_var` (quando presente) é a variável emitida em `:root` por
     * Arena\Options::cssTokens(); `unit` é sufixada ao valor numérico.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function fields(): array {
        return [
            // ---------------------------------------------------------- cores
            'arena_topbar_bg' => [
                'group'       => 'cores',
                'label'       => 'Fundo da barra superior',
                'description' => 'A faixa escura acima do logo. Padrão do tema: #0b0b0b.',
                'type'        => 'color',
                'default'     => '#0b0b0b',
                'css_var'     => '--arena-topbar-bg',
            ],
            'arena_header_bg' => [
                'group'       => 'cores',
                'label'       => 'Fundo do cabeçalho',
                'description' => 'Atenção: a logo tem letras claras, então um fundo claro aqui pode deixá-la ilegível.',
                'type'        => 'color',
                'default'     => '#0b0b0b',
                'css_var'     => '--arena-header-bg',
            ],
            'arena_menu_color' => [
                'group'       => 'cores',
                'label'       => 'Cor dos itens do menu',
                'description' => 'Texto do menu principal sobre a barra escura.',
                'type'        => 'color',
                'default'     => '#ffffff',
                'css_var'     => '--arena-menu-color',
            ],
            'arena_menu_hover' => [
                'group'       => 'cores',
                'label'       => 'Cor do menu ao passar o mouse',
                'description' => 'Vazio = usa a cor de destaque do tema.',
                'type'        => 'color',
                'default'     => '',
                'css_var'     => '--arena-menu-hover',
            ],
            'arena_footer_bg' => [
                'group'       => 'cores',
                'label'       => 'Fundo do rodapé',
                'description' => 'Padrão do tema: #1b1b1b.',
                'type'        => 'color',
                'default'     => '#1b1b1b',
                'css_var'     => '--arena-footer-bg',
            ],
            'arena_footer_text' => [
                'group'       => 'cores',
                'label'       => 'Cor do texto do rodapé',
                'description' => 'Padrão do tema: #cccccc.',
                'type'        => 'color',
                'default'     => '#cccccc',
                'css_var'     => '--arena-footer-text',
            ],

            // ----------------------------------------------------- tipografia
            'arena_font_size_base' => [
                'group'       => 'tipografia',
                'label'       => 'Tamanho base do texto',
                'description' => 'Em pixels. Afeta o corpo das matérias e o que herda dele. Vazio = padrão do navegador (16px).',
                'type'        => 'number',
                'default'     => '',
                'min'         => 13,
                'max'         => 22,
                'step'        => 1,
                'unit'        => 'px',
                'css_var'     => '--arena-font-size-base',
            ],
            'arena_line_height' => [
                'group'       => 'tipografia',
                'label'       => 'Altura de linha do texto',
                'description' => 'Espaço entre linhas no corpo do texto. Entre 1.3 e 2.0. Vazio = padrão do tema.',
                'type'        => 'number',
                'default'     => '',
                'min'         => 1.3,
                'max'         => 2.0,
                'step'        => 0.05,
                'css_var'     => '--arena-line-height',
            ],
            'arena_headline_weight' => [
                'group'       => 'tipografia',
                'label'       => 'Peso dos títulos',
                'description' => 'Espessura das manchetes e títulos de matéria.',
                'type'        => 'select',
                'default'     => '',
                'choices'     => [
                    ''    => 'Padrão do tema (negrito)',
                    '500' => '500 — médio',
                    '600' => '600 — semibold',
                    '700' => '700 — negrito',
                    '800' => '800 — extra negrito',
                    '900' => '900 — black',
                ],
                'css_var'     => '--arena-headline-weight',
            ],
            'arena_post_title_size' => [
                'group'       => 'tipografia',
                'label'       => 'Tamanho do título da matéria',
                'description' => 'Em pixels, no desktop. Vazio = padrão do tema.',
                'type'        => 'number',
                'default'     => '',
                'min'         => 22,
                'max'         => 56,
                'step'        => 1,
                'unit'        => 'px',
                'css_var'     => '--arena-post-title-size',
            ],

            // --------------------------------------------------------- layout
            'arena_site_width' => [
                'group'       => 'layout',
                'label'       => 'Largura da caixa de conteúdo',
                'description' => 'Em pixels. Padrão do tema: 1200px (igual ao site de referência).',
                'type'        => 'number',
                'default'     => '',
                'min'         => 960,
                'max'         => 1600,
                'step'        => 10,
                'unit'        => 'px',
                'css_var'     => '--arena-site-width',
            ],
            'arena_block_spacing' => [
                'group'       => 'layout',
                'label'       => 'Espaço entre blocos',
                'description' => 'Distância vertical entre as seções da home e das listagens. Padrão do tema: 50px.',
                'type'        => 'number',
                'default'     => '',
                'min'         => 16,
                'max'         => 96,
                'step'        => 2,
                'unit'        => 'px',
                'css_var'     => '--arena-block-spacing',
            ],
            'arena_sidebar_position' => [
                'group'       => 'layout',
                'label'       => 'Posição da sidebar',
                'description' => 'Vale para matérias, páginas, categorias e busca.',
                'type'        => 'select',
                'default'     => Options::DEFAULT_SIDEBAR_POSITION,
                'choices'     => [
                    'right' => 'À direita do conteúdo',
                    'left'  => 'À esquerda do conteúdo',
                    'none'  => 'Sem sidebar (conteúdo em largura total)',
                ],
            ],
            'arena_cards_per_row' => [
                'group'       => 'layout',
                'label'       => 'Cards por linha nas listagens',
                'description' => 'Usado quando o bloco não define a própria quantidade de colunas.',
                'type'        => 'select',
                'default'     => '',
                'choices'     => [
                    ''  => 'Padrão de cada bloco',
                    '2' => '2 por linha',
                    '3' => '3 por linha',
                    '4' => '4 por linha',
                ],
            ],

            // --------------------------------------------------------- blocos
            'arena_block_title_color' => [
                'group'       => 'blocos',
                'label'       => 'Cor dos títulos de bloco',
                'description' => 'A tarja colorida ao lado de "Últimas notícias", "Destaques" etc. Vazio = cor de destaque.',
                'type'        => 'color',
                'default'     => '',
                'css_var'     => '--arena-block-title-color',
            ],
            'arena_dark_latest_row' => [
                'group'       => 'blocos',
                'label'       => 'Faixa escura em "Últimas notícias"',
                'description' => 'Desligado, o bloco fica com fundo claro como os demais.',
                'type'        => 'checkbox',
                'default'     => '1',
            ],
            'arena_items_per_block' => [
                'group'       => 'blocos',
                'label'       => 'Itens por bloco',
                'description' => 'Quantidade padrão de posts em cada listagem, quando o bloco não especifica.',
                'type'        => 'number',
                'default'     => '',
                'min'         => 3,
                'max'         => 20,
                'step'        => 1,
            ],
            'arena_default_thumbnail' => [
                'group'       => 'blocos',
                'label'       => 'Imagem padrão dos cards',
                'description' => 'Usada quando a matéria não tem imagem destacada. Informe o ID do anexo da biblioteca de mídia.',
                'type'        => 'image',
                'default'     => '',
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function field(string $id): ?array {
        return self::fields()[$id] ?? null;
    }

    /** @return array<string, array<string, mixed>> */
    public static function fieldsByGroup(string $group): array {
        return array_filter(self::fields(), static fn (array $f): bool => ($f['group'] ?? '') === $group);
    }

    /**
     * Valor efetivo de uma opção: `theme_mod` → opção do ACF (quando houver) →
     * vazio. Vazio significa "usar o padrão do tema", e é isso que faz o CSS
     * cair no `var(--token, valor-de-hoje)`.
     *
     * Sempre passa pelo saneamento do tipo: um valor inválido salvo à mão no
     * banco nunca chega ao HTML.
     */
    public static function value(string $id): string {
        $campo = self::field($id);
        if ($campo === null) {
            return '';
        }

        $mod = get_theme_mod($id);
        $sanitizado = self::sanitize($id, $mod);
        if ($sanitizado !== '') {
            return $sanitizado;
        }

        // Compatibilidade: sites que configuraram pelo painel do ACF PRO.
        $acf = Options::get($id, '');
        return self::sanitize($id, $acf);
    }

    /** Igual a value(), mas devolve o `default` do campo quando nada foi salvo. */
    public static function valueOrDefault(string $id): string {
        $valor = self::value($id);
        if ($valor !== '') {
            return $valor;
        }

        $campo = self::field($id);
        return (string) ($campo['default'] ?? '');
    }

    /**
     * Saneamento por tipo. Devolve `''` para qualquer coisa inválida — que o
     * chamador interpreta como "não configurado".
     */
    public static function sanitize(string $id, mixed $valor): string {
        $campo = self::field($id);
        if ($campo === null || $valor === null || $valor === false || $valor === '') {
            return '';
        }

        switch ((string) ($campo['type'] ?? 'text')) {
            case 'color':
                if (!is_string($valor)) {
                    return '';
                }
                // Aceita hex digitado sem "#" (é o que a pessoa naturalmente
                // escreve num campo de texto); o resto continua rejeitado.
                $hex = trim($valor);
                if ($hex !== '' && $hex[0] !== '#' && preg_match('/^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $hex) === 1) {
                    $hex = '#' . $hex;
                }
                return Options::normalizeHexColor($hex) ?? '';

            case 'select':
                /*
                 * `array_map('strval', ...)`: chaves de array em PHP viram
                 * INTEIRO quando são numéricas ('800' => 800), então uma
                 * comparação estrita contra a string enviada pelo formulário
                 * falharia silenciosamente e todo select numérico (peso do
                 * título, cards por linha) seria descartado como inválido.
                 */
                $opcoes = array_map('strval', array_keys((array) ($campo['choices'] ?? [])));
                $v = is_string($valor) ? $valor : (string) $valor;
                return in_array($v, $opcoes, true) ? $v : '';

            case 'number':
                if (!is_numeric($valor)) {
                    return '';
                }
                $n = (float) $valor;
                $min = isset($campo['min']) ? (float) $campo['min'] : null;
                $max = isset($campo['max']) ? (float) $campo['max'] : null;
                if (($min !== null && $n < $min) || ($max !== null && $n > $max)) {
                    return ''; // fora da faixa = não configurado, nunca um valor absurdo no CSS
                }
                // Mantém decimais só quando existem (1.65 continua 1.65; 16.0 vira 16).
                return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

            case 'checkbox':
                return in_array($valor, [true, 1, '1', 'on', 'true'], true) ? '1' : '0';

            case 'image':
                $id_anexo = Options::parseLogoId($valor);
                return $id_anexo === null ? '' : (string) $id_anexo;

            default:
                return is_string($valor) ? sanitize_text_field($valor) : '';
        }
    }

    /**
     * Variáveis CSS a emitir em `:root`. Só entram as opções realmente
     * configuradas — o resto continua vindo do `main.css`, sem override.
     *
     * @return array<string, string>
     */
    public static function cssTokens(): array {
        $tokens = [];

        foreach (self::fields() as $id => $campo) {
            $var = (string) ($campo['css_var'] ?? '');
            if ($var === '') {
                continue;
            }

            $valor = self::value($id);
            if ($valor === '') {
                continue;
            }

            $tokens[$var] = $valor . (string) ($campo['unit'] ?? '');
        }

        // A cor do menu no hover e a dos títulos de bloco herdam a cor de
        // destaque quando não configuradas — resolvido aqui para o CSS não
        // precisar de `var()` encadeado em cascata longa.
        return $tokens;
    }

    /** A faixa escura de "Últimas notícias" está ligada? (padrão: sim) */
    public static function darkLatestRowEnabled(): bool {
        $valor = self::value('arena_dark_latest_row');
        return $valor === '' ? true : $valor === '1';
    }

    /** Quantidade padrão de itens por bloco, ou null quando não configurada. */
    public static function itemsPerBlock(): ?int {
        $valor = self::value('arena_items_per_block');
        return $valor === '' ? null : (int) $valor;
    }

    /** Colunas padrão nas listagens, ou null quando cada bloco decide. */
    public static function cardsPerRow(): ?int {
        $valor = self::value('arena_cards_per_row');
        return $valor === '' ? null : (int) $valor;
    }

    /** ID do anexo usado como imagem padrão dos cards, ou null. */
    public static function defaultThumbnailId(): ?int {
        $valor = self::value('arena_default_thumbnail');
        return $valor === '' ? null : (int) $valor;
    }
}
