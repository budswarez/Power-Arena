<?php
declare(strict_types=1);

/**
 * Guarda a responsividade das colunas do WPBakery no mobile.
 *
 * BUG RELATADO: na home, a linha de três colunas (Hardware / VALORANT / Free
 * Fire) não empilhava no celular — as três ficavam lado a lado com ~130px cada
 * num viewport de 390px, uma palavra por linha, e a página estourava 49px para
 * a direita.
 *
 * CAUSA RAIZ (medida, não inferida): a instalação tem a opção do WPBakery
 * `wpb_js_not_responsive_css = 1` ("desabilitar elementos responsivos"),
 * herdada da época do tema Publisher. Com ela, o WordPress imprime
 * `vc_non_responsive` na classe do `<body>`, e o `js_composer.min.css` traz
 *
 *     .vc_non_responsive .vc_row .vc_col-sm-4 { width: 33.33333333% }
 *     .vc_non_responsive .vc_row .vc_col-sm-4 { float: left }
 *
 * SEM nenhum `@media` — ou seja, largura fixa de 1/3 em QUALQUER tela. As
 * regras responsivas nativas do próprio js_composer (dentro de
 * `@media (min-width:768px)`) continuam lá, mas nunca chegam a importar,
 * porque a variante `vc_non_responsive` é mais específica e incondicional.
 *
 * O Publisher compensava isso com CSS mobile próprio. O Arena não tinha nada
 * equivalente — daí a regressão só aparecer depois da troca de tema.
 *
 * POR QUE O TEMA CORRIGE, EM VEZ DE A OPÇÃO SER DESLIGADA: desligar
 * `wpb_js_not_responsive_css` resolveria no site do cliente, mas é
 * configuração global do construtor, não do tema — e um tema revendável não
 * pode depender de um ajuste que qualquer administrador pode ligar de volta
 * sem saber a consequência. O tema é responsável pelo próprio comportamento
 * responsivo.
 *
 * Os limites (767/991/1199px) reproduzem exatamente o que o js_composer faria
 * se a opção estivesse desligada: `col-sm-*` empilha abaixo de 768,
 * `col-md-*` abaixo de 992, `col-lg-*` abaixo de 1200. `vc_col-xs-*` fica de
 * fora DE PROPÓSITO: no Bootstrap, `xs` significa "inclusive em telas
 * pequenas", ou seja, é escolha explícita de quem montou a página.
 */
final class WpBakeryResponsiveGuardTest extends WP_UnitTestCase {
    /** Os três pares (prefixo de classe, limite superior em px) que devem empilhar. */
    private const BREAKPOINTS = [
        ['vc_col-sm-', 767],
        ['vc_col-md-', 991],
        ['vc_col-lg-', 1199],
    ];

    private function css(): string {
        $path = ARENA_DIR . '/assets/src/css/main.css';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** CSS sem comentários — os comentários deste projeto contêm CSS ilustrativo. */
    private function cssSemComentarios(): string {
        return (string) preg_replace('#/\*.*?\*/#s', '', $this->css());
    }

    /**
     * Existe, para cada breakpoint, uma regra que devolve as colunas do
     * WPBakery à largura total quando o `<body>` está em `vc_non_responsive`.
     *
     * A asserção é sobre o COMPORTAMENTO (largura 100% + sem float), não sobre
     * um seletor literal, para a regra poder ser reescrita sem falso alarme.
     */
    public function test_colunas_do_wpbakery_empilham_no_mobile_mesmo_em_vc_non_responsive(): void {
        $css = $this->cssSemComentarios();

        foreach (self::BREAKPOINTS as [$prefixo, $limite]) {
            $encontrou = false;

            // Cada bloco @media com max-width compatível com o breakpoint.
            preg_match_all('/@media[^{]*max-width:\s*(\d+)px[^{]*\{/i', $css, $medias, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

            foreach ($medias as $media) {
                if ((int) $media[1][0] !== $limite) {
                    continue;
                }

                $corpo = $this->corpoDoBloco($css, (int) $media[0][1] + strlen($media[0][0]) - 1);

                if (
                    str_contains($corpo, 'vc_non_responsive')
                    && str_contains($corpo, $prefixo)
                    && preg_match('/width:\s*100%/', $corpo) === 1
                    && preg_match('/float:\s*none/', $corpo) === 1
                ) {
                    $encontrou = true;
                    break;
                }
            }

            $this->assertTrue(
                $encontrou,
                "Falta a regra que empilha `{$prefixo}*` dentro de " .
                    "`@media (max-width: {$limite}px)` (precisa de `width: 100%` e `float: none`, " .
                    'escopada em `vc_non_responsive`). Sem ela, a opção ' .
                    '`wpb_js_not_responsive_css` do WPBakery mantém as colunas lado a lado no ' .
                    'celular e a home estoura horizontalmente.'
            );
        }
    }

    /**
     * A regra PRECISA ficar fora de qualquer `@layer`.
     *
     * Comprovado por medição na home de produção, a 390px, injetando a MESMA
     * regra das duas formas:
     *
     *   - dentro de `@layer components` -> colunas continuaram com 130px
     *   - fora de qualquer camada       -> colunas foram para 390px
     *
     * Motivo: o `js_composer.min.css` não usa camadas, e na cascata de camadas
     * uma declaração SEM camada vence qualquer declaração DENTRO de uma camada
     * na mesma origem, independentemente da especificidade. É a mesma armadilha
     * que já derrotou as regras de alinhamento de mídia (ver ADR-007 e
     * StyleGuardTest::test_media_alignment_rules_live_outside_any_cascade_layer).
     *
     * Ou seja: mover este bloco para dentro de `@layer` não é refatoração
     * inofensiva — desliga a correção sem quebrar nada visível nos testes de
     * marcação.
     */
    public function test_regra_responsiva_do_wpbakery_fica_fora_de_qualquer_camada(): void {
        $css = $this->cssSemComentarios();

        $pilha = [];      // um booleano por bloco aberto: "foi aberto por @layer?"
        $cabeca = '';     // texto acumulado desde o último delimitador
        $emCamada = [];

        for ($i = 0, $len = strlen($css); $i < $len; $i++) {
            $char = $css[$i];

            if ($char === '{') {
                $pilha[] = str_contains($cabeca, '@layer');
                $cabeca = '';
                continue;
            }

            if ($char === '}') {
                array_pop($pilha);
                $cabeca = '';
                continue;
            }

            if ($char === ';') {
                $cabeca = '';
                continue;
            }

            $cabeca .= $char;

            if (!in_array(true, $pilha, true)) {
                continue; // fora de camada: exatamente onde a regra deve estar
            }

            if (str_contains($cabeca, 'vc_non_responsive')) {
                $emCamada[] = trim($cabeca);
                $cabeca = '';
            }
        }

        $this->assertSame(
            [],
            $emCamada,
            'Regra `vc_non_responsive` dentro de `@layer`: o CSS do js_composer é sem camada e vence ' .
                'qualquer declaração em camada, independentemente da especificidade — as colunas voltam ' .
                'a ficar lado a lado no celular. Medido a 390px: dentro da camada as colunas ficam com ' .
                '130px; fora, com 390px. Seletores em camada: ' . implode(' | ', $emCamada)
        );
    }

    /**
     * `vc_col-xs-*` NÃO pode ser forçado a 100%: no Bootstrap, `xs` significa
     * "vale também nas telas pequenas", então é uma escolha deliberada de quem
     * montou a página. Empilhar isso seria o tema passando por cima do editor.
     */
    public function test_nao_forca_empilhamento_das_colunas_xs(): void {
        $css = $this->cssSemComentarios();

        preg_match_all('/([^{}]*vc_col-xs-[^{]*)\{([^}]*)\}/', $css, $regras, PREG_SET_ORDER);

        foreach ($regras as $regra) {
            $this->assertDoesNotMatchRegularExpression(
                '/width:\s*100%/',
                $regra[2],
                'O tema não deve forçar `vc_col-xs-*` a 100%: `xs` é a escolha explícita de manter as ' .
                    "colunas lado a lado em telas pequenas. Seletor: " . trim($regra[1])
            );
        }
    }

    /**
     * Devolve o conteúdo do bloco cujo `{` está em $posAbertura,
     * respeitando aninhamento (um `@media` contém regras com suas chaves).
     */
    private function corpoDoBloco(string $css, int $posAbertura): string {
        $prof = 0;
        $len = strlen($css);

        for ($i = $posAbertura; $i < $len; $i++) {
            if ($css[$i] === '{') {
                $prof++;
            } elseif ($css[$i] === '}') {
                $prof--;
                if ($prof === 0) {
                    return substr($css, $posAbertura + 1, $i - $posAbertura - 1);
                }
            }
        }

        return '';
    }
}
