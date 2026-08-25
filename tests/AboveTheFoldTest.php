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
     * prioridade. É o caso dos tiles compactos do mosaico e do logo — promover
     * todos os cards da home ao mesmo tempo anula o próprio conceito.
     */
    public function test_acima_da_dobra_sem_prioridade(): void {
        $attr = \Arena\Media::markAboveTheFold(['class' => 'hero-tile__img'], false);

        $this->assertSame('eager', $attr['loading'], 'Tem de sair do lazy-load mesmo sem prioridade.');
        $this->assertStringContainsString('skip-lazy', $attr['class']);
        $this->assertArrayNotHasKey(
            'fetchpriority',
            $attr,
            'Uma imagem fora do conjunto candidato a LCP não deve pedir prioridade alta.'
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
            '/[\'"]above_fold[\'"]\s*=>\s*\$blocoAcimaDaDobra\s*&&\s*\$index\s*<=\s*\$aboveFoldCount/',
            $php,
            'modern-grid.php deve marcar os N primeiros tiles ($aboveFoldCount) E somente se este for o ' .
                'primeiro bloco de listagem da requisição (o trinco).'
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
            '/[\'"]high_priority[\'"]\s*=>\s*\$blocoAcimaDaDobra\s*&&\s*\$index\s*<=\s*\$firstRowSize/',
            $php,
            'Os dois tiles grandes da primeira linha podem ser eleitos LCP e precisam de prioridade alta.'
        );
        $this->assertMatchesRegularExpression(
            '/markAboveTheFold\(\$imgAttr,\s*\$highPriority\)/',
            $hero,
            'hero.php deve respeitar a decisão de prioridade tomada pelo layout.'
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

    /**
     * Na home o tema reivindica a vaga de `fetchpriority="high"` antes do
     * conteúdo, para o core não promover o banner do WPBakery (122 KB, exibido em
     * 352×106) em vez do tile do mosaico que é o LCP.
     */
    public function test_home_reivindica_a_prioridade_antes_do_conteudo(): void {
        \Arena\Setup::register();

        $this->assertNotFalse(
            has_action('template_redirect', [\Arena\Setup::class, 'claimHighPriorityImage']),
            'Sem este hook, o core dá fetchpriority=high para a primeira imagem grande do conteúdo — ' .
                'na home, o banner de 122 KB, que não é o elemento LCP. Medido em A/B: com o hook, ' .
                'mobile 93 / desktop 97; sem ele, mobile 89 / desktop 99. Mantido ligado porque num ' .
                'portal de notícias o tráfego mobile domina e é o score mais fraco.'
        );
    }

    /** Fora da home a heurística do core acerta e não deve ser desligada. */
    public function test_prioridade_so_e_reivindicada_na_home(): void {
        $this->assertTrue(wp_high_priority_element_flag(), 'Pré-condição: a flag começa disponível.');

        $post = self::factory()->post->create_and_get();
        $this->go_to(get_permalink($post));
        \Arena\Setup::claimHighPriorityImage();

        $this->assertTrue(
            wp_high_priority_element_flag(),
            'Numa matéria a imagem de destaque renderiza antes do conteúdo, então a heurística do core ' .
                'já escolhe certo — desligá-la ali seria perder otimização de graça.'
        );
    }

    /**
     * O trinco: só o PRIMEIRO bloco de listagem da requisição pode marcar imagens
     * como acima da dobra. Sem ele, cada bloco marcava o próprio primeiro card e a
     * home servia **6** imagens com `fetchpriority="high"` — prioridade em seis
     * imagens não é prioridade, e desde o `skip-lazy` elas também saíam do
     * lazy-load, disputando banda com o LCP de verdade.
     */
    public function test_apenas_o_primeiro_bloco_reivindica_acima_da_dobra(): void {
        \Arena\Media::resetAboveTheFoldBlock();

        $this->assertTrue(\Arena\Media::claimAboveTheFoldBlock(), 'O 1º bloco deve conseguir.');
        $this->assertFalse(\Arena\Media::claimAboveTheFoldBlock(), 'O 2º bloco não pode.');
        $this->assertFalse(\Arena\Media::claimAboveTheFoldBlock(), 'Nem o 3º.');

        \Arena\Media::resetAboveTheFoldBlock();
        $this->assertTrue(\Arena\Media::claimAboveTheFoldBlock(), 'Depois do reset volta a liberar.');
    }

    /**
     * Os cards genéricos de listagem não podem cair em `is_first` como padrão —
     * o primeiro card de um bloco lá embaixo da página não está acima da dobra.
     */
    public function test_cards_de_listagem_tem_padrao_seguro(): void {
        foreach (['featured', 'list', 'text'] as $card) {
            $php = (string) file_get_contents(ARENA_DIR . "/template-parts/card/{$card}.php");

            $this->assertMatchesRegularExpression(
                "/\\\$aboveFold\s*=\s*\(bool\)\s*\(\\\$args\['above_fold'\]\s*\?\?\s*false\)/",
                $php,
                "card/{$card}.php deve usar `above_fold` com padrão FALSE — cair em `is_first` faz " .
                    'cada listagem da página marcar um card como prioritário.'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/if\s*\(\$isFirst\)\s*\{\s*\n\s*\/\/[^\n]*\n\s*\/\/[^\n]*\n\s*\$imgAttr = \\\\Arena\\\\Media::markAboveTheFold/',
                $php,
                "card/{$card}.php não pode marcar por `is_first`."
            );
        }
    }

    /** Todas as listagens precisam reivindicar o trinco, senão nenhuma marca nada. */
    public function test_todas_as_listagens_usam_o_trinco(): void {
        $layouts = ['grid', 'blog', 'archive', 'mix', 'modern-grid', 'modern-grid-1'];

        foreach ($layouts as $l) {
            $php = (string) file_get_contents(ARENA_DIR . "/template-parts/listing/{$l}.php");

            $this->assertStringContainsString(
                'claimAboveTheFoldBlock()',
                $php,
                "listing/{$l}.php deve reivindicar o trinco para decidir se pode marcar acima da dobra."
            );
            $this->assertStringContainsString(
                'above_fold',
                $php,
                "listing/{$l}.php deve repassar `above_fold` para o card."
            );
        }
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
