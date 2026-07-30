<?php
declare(strict_types=1);

/**
 * Guarda a blindagem das imagens ACIMA DA DOBRA contra lazy-loaders.
 *
 * BUG MEDIDO (auditoria de PageSpeed, 30/07): o elemento LCP da home mobile era
 * uma imagem do mosaico com a classe `lazyloaded` — o lazy-loader do EWWW
 * (lazysizes) reescrevia o `src` para um placeholder base64, movia a URL real
 * para `data-src` e **ignorava o `loading="eager"` que o tema já declarava**.
 * Resultado, medido com throttling estilo Lighthouse (4G lento + CPU 4×): a
 * imagem terminava em 6.648 ms e o LCP era 6.680 ms. O logotipo, de 3 KB e no
 * topo da página, terminava em 5.668 ms pelo mesmo motivo.
 *
 * A correção é o tema declarar `skip-lazy` junto de `eager` — string que o EWWW
 * tem na própria lista de exclusões (`classes/class-lazy-load.php`) e que WP
 * Rocket, Perfmatters e o lazysizes também reconhecem.
 *
 * Estes testes existem porque a regressão é INVISÍVEL: sem eles, alguém
 * "limpando" o helper de volta para `fetchpriority` + `loading` deixaria a
 * marcação igualzinha aos olhos e o LCP voltaria a 6,7 s.
 */
final class AboveTheFoldTest extends WP_UnitTestCase {
    public function test_marca_prioridade_sem_lazy_e_com_skip_lazy(): void {
        $attr = \Arena\Media::markAboveTheFold(['class' => 'attachment-arena-card hero-tile__img']);

        $this->assertSame('high', $attr['fetchpriority']);
        $this->assertSame('eager', $attr['loading']);
        $this->assertStringContainsString(
            'skip-lazy',
            $attr['class'],
            'Sem `skip-lazy` o lazy-loader do EWWW troca o src por um placeholder base64 e o LCP ' .
                'só começa a baixar depois do JavaScript — medido: LCP de 6,7 s na home mobile.'
        );
    }

    /** As classes que o chamador passou não podem ser perdidas (CSS depende delas). */
    public function test_preserva_as_classes_existentes(): void {
        $attr = \Arena\Media::markAboveTheFold(['class' => 'attachment-arena-card hero-tile__img']);

        $this->assertStringContainsString('attachment-arena-card', $attr['class']);
        $this->assertStringContainsString('hero-tile__img', $attr['class']);
    }

    /** Sem `class` na entrada não deve sobrar espaço solto nem chave ausente. */
    public function test_funciona_sem_classe_previa(): void {
        $attr = \Arena\Media::markAboveTheFold(['decoding' => 'async']);

        $this->assertSame('skip-lazy', $attr['class']);
        $this->assertSame('async', $attr['decoding'], 'Outros atributos passados pelo chamador devem sobreviver.');
    }

    /**
     * Acima da dobra SEM ser candidato a LCP: sai do lazy, mas não pede
     * prioridade. É o caso dos tiles 2+ da primeira linha do mosaico e do logo —
     * três `fetchpriority="high"` ao mesmo tempo anulam o próprio conceito.
     */
    public function test_acima_da_dobra_sem_prioridade(): void {
        $attr = \Arena\Media::markAboveTheFold(['class' => 'hero-tile__img'], false);

        $this->assertSame('eager', $attr['loading'], 'Tem de sair do lazy-load mesmo sem prioridade.');
        $this->assertStringContainsString('skip-lazy', $attr['class']);
        $this->assertArrayNotHasKey(
            'fetchpriority',
            $attr,
            'Só UMA imagem por página deve pedir prioridade alta — a candidata a LCP.'
        );
    }

    /**
     * A primeira LINHA do mosaico (2 tiles) precisa sair do lazy, não só o 1º
     * tile. Medido: os tiles visíveis têm área idêntica (58.282 px²), então
     * proteger apenas o primeiro fazia o LCP escapar para o segundo, que
     * continuava lazy e só baixava em 5,4 s.
     */
    public function test_mosaico_marca_a_primeira_linha_inteira(): void {
        $php = (string) file_get_contents(ARENA_DIR . '/template-parts/listing/modern-grid.php');

        $this->assertMatchesRegularExpression(
            '/[\'"]above_fold[\'"]\s*=>\s*\$index\s*<=\s*\$aboveFoldCount/',
            $php,
            'modern-grid.php deve marcar como acima da dobra os N primeiros tiles ($aboveFoldCount).'
        );
        $this->assertMatchesRegularExpression(
            '/\$aboveFoldCount\s*=\s*3\s*;/',
            $php,
            'O limite medido é 3 tiles (412x823, mobile). Mudar isso exige re-medir: um tile de mesma ' .
                'área deixado em lazy-load arrasta o LCP para ~6,5 s; e marcar demais faz as imagens ' .
                'disputarem banda com o próprio LCP.'
        );

        $hero = (string) file_get_contents(ARENA_DIR . '/template-parts/card/hero.php');
        $this->assertMatchesRegularExpression(
            '/markAboveTheFold\(\$imgAttr,\s*\$isFirst\)/',
            $hero,
            'hero.php deve passar $isFirst como flag de prioridade, para só o 1º tile pedir fetchpriority.'
        );
        $this->assertStringContainsString(
            '$aboveFold',
            $hero,
            'hero.php deve decidir pelo flag `above_fold`, não só por `is_first`.'
        );
    }

    /**
     * O logotipo leva `skip-lazy` + `eager`, mas **não** `fetchpriority` — essa
     * prioridade pertence ao elemento LCP, e o logo competindo com ele piora o
     * carregamento.
     */
    public function test_logo_pula_lazy_mas_nao_disputa_prioridade(): void {
        $attr = \Arena\Setup::logoIsAboveTheFold(['class' => 'custom-logo']);

        $this->assertStringContainsString('skip-lazy', $attr['class']);
        $this->assertStringContainsString('custom-logo', $attr['class']);
        $this->assertSame('eager', $attr['loading']);
        $this->assertArrayNotHasKey(
            'fetchpriority',
            $attr,
            'O logo não é o LCP; dar-lhe fetchpriority=high faz ele disputar banda com quem é.'
        );
    }

    /** O filtro do core precisa estar realmente ligado, senão o método é código morto. */
    public function test_o_filtro_do_core_esta_registrado(): void {
        \Arena\Setup::register();

        $this->assertNotFalse(
            has_filter('get_custom_logo_image_attributes', [\Arena\Setup::class, 'logoIsAboveTheFold']),
            'Sem o filtro `get_custom_logo_image_attributes`, o logo volta a ser lazy-loaded.'
        );
    }

    /** Entrada malformada (outro plugin filtrando antes) não pode gerar TypeError. */
    public function test_logo_tolera_entrada_malformada(): void {
        $this->assertSame(['loading' => 'eager', 'class' => 'skip-lazy'], \Arena\Setup::logoIsAboveTheFold(null));
        $this->assertSame(['loading' => 'eager', 'class' => 'skip-lazy'], \Arena\Setup::logoIsAboveTheFold('texto'));
    }

    /**
     * Nenhum dos 5 pontos que renderizam imagem acima da dobra pode voltar a
     * montar os atributos "na mão" — é assim que `skip-lazy` se perde.
     */
    public function test_os_templates_usam_o_helper_em_vez_de_atributos_soltos(): void {
        $arquivos = [
            'template-parts/card/featured.php',
            'template-parts/card/hero.php',
            'template-parts/card/list.php',
            'template-parts/card/text.php',
            'template-parts/single/featured.php',
        ];

        foreach ($arquivos as $rel) {
            $caminho = ARENA_DIR . '/' . $rel;
            $this->assertFileExists($caminho);
            $php = (string) file_get_contents($caminho);

            $this->assertStringContainsString(
                'markAboveTheFold',
                $php,
                "{$rel} deve marcar a imagem acima da dobra por Arena\\Media::markAboveTheFold()."
            );

            // Comentários citam `fetchpriority` de propósito; o que não pode
            // voltar é a ATRIBUIÇÃO direta do atributo.
            $this->assertDoesNotMatchRegularExpression(
                '/\$imgAttr\[[\'"]fetchpriority[\'"]\]\s*=/',
                $php,
                "{$rel} voltou a definir `fetchpriority` na mão — nesse caminho o `skip-lazy` não é " .
                    'aplicado e o lazy-loader assume a imagem de novo.'
            );
        }
    }
}
