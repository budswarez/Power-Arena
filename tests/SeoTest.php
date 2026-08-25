<?php
declare(strict_types=1);

use Arena\Seo;

/**
 * Cobre o resolvedor de breadcrumb.
 *
 * O bug que motivou esta classe: sete templates chamavam `yoast_breadcrumb()`
 * direto e, quando o site trocou o Yoast pelo Rank Math, a trilha desapareceu
 * sem erro nenhum — o `function_exists()` só pulava a chamada. O Rank Math não
 * insere breadcrumb sozinho; ele exige que o tema chame a função dele.
 *
 * No ambiente de teste existe o stub `yoast_breadcrumb()` (tests/bootstrap.php)
 * e NÃO existem as funções do Rank Math, então o provedor esperado aqui é o
 * Yoast — o que também prova que a compatibilidade com quem continua no Yoast
 * não foi perdida na migração.
 */
final class SeoTest extends WP_UnitTestCase {
    public function test_detects_the_available_provider(): void {
        $this->assertSame(
            'yoast',
            Seo::breadcrumbProvider(),
            'no ambiente de teste só o stub do Yoast existe'
        );
    }

    public function test_returns_html_from_the_active_plugin(): void {
        $html = Seo::breadcrumbHtml();

        $this->assertNotSame('', $html);
        $this->assertStringContainsString('<a href=', $html);
    }

    public function test_breadcrumb_wraps_the_html_in_the_given_tags(): void {
        ob_start();
        $renderizou = Seo::breadcrumb('<nav class="arena-breadcrumb">', '</nav>');
        $saida = (string) ob_get_clean();

        $this->assertTrue($renderizou);
        $this->assertStringStartsWith('<nav class="arena-breadcrumb">', $saida);
        $this->assertStringEndsWith('</nav>', $saida);
    }

    /**
     * Sem plugin de SEO, nada é renderizado — nem o wrapper vazio, que deixaria
     * um `<nav>` órfão no HTML (ruim para leitores de tela: um marco de
     * navegação sem conteúdo).
     */
    public function test_renders_nothing_when_there_is_no_breadcrumb(): void {
        add_filter('arena_breadcrumb_html', '__return_empty_string');

        ob_start();
        $renderizou = Seo::breadcrumb();
        $saida = (string) ob_get_clean();

        remove_filter('arena_breadcrumb_html', '__return_empty_string');

        $this->assertFalse($renderizou);
        $this->assertSame('', $saida, 'nem o wrapper deve sair quando não há trilha');
    }

    /** O filtro permite ao tema filho substituir a trilha por completo. */
    public function test_child_theme_can_replace_the_breadcrumb_via_filter(): void {
        $substituto = static fn (): string => '<span>trilha própria</span>';
        add_filter('arena_breadcrumb_html', $substituto);

        ob_start();
        Seo::breadcrumb('<nav>', '</nav>');
        $saida = (string) ob_get_clean();

        remove_filter('arena_breadcrumb_html', $substituto);

        $this->assertSame('<nav><span>trilha própria</span></nav>', $saida);
    }

    public function test_post_description_prefers_authored_seo_metadata(): void {
        $postId = $this->factory()->post->create([
            'post_title'   => 'Post com descrição SEO',
            'post_excerpt' => 'Resumo do excerpt que não deve ser usado aqui.',
            'post_status'  => 'publish',
        ]);
        update_post_meta($postId, 'rank_math_description', 'Descrição curta para a listagem.');

        $this->assertSame('Descrição curta para a listagem.', Seo::postDescription((int) $postId));
    }

    public function test_post_description_falls_back_to_excerpt_without_seo_metadata(): void {
        $postId = $this->factory()->post->create([
            'post_title'   => 'Post sem descrição SEO',
            'post_excerpt' => 'Resumo editorial usado como fallback.',
            'post_status'  => 'publish',
        ]);

        $this->assertSame('Resumo editorial usado como fallback.', Seo::postDescription((int) $postId));
    }

    /** Todo template que mostra breadcrumb precisa usar o resolvedor, não o plugin direto. */
    public function test_every_template_uses_the_resolver(): void {
        $templates = ['404.php', 'archive.php', 'attachment.php', 'index.php', 'page.php', 'search.php', 'single.php'];

        foreach ($templates as $arquivo) {
            $caminho = ARENA_DIR . '/' . $arquivo;
            $this->assertFileExists($caminho);
            $codigo = (string) file_get_contents($caminho);

            $this->assertStringContainsString(
                'Seo::breadcrumb()',
                $codigo,
                "{$arquivo} não chama o resolvedor de breadcrumb"
            );

            // Chamada direta ao plugin (fora de comentário) não pode voltar.
            $this->assertSame(
                0,
                preg_match('/^\s*(if \(function_exists\(.yoast_breadcrumb.\)|yoast_breadcrumb\()/m', $codigo),
                "{$arquivo} voltou a chamar yoast_breadcrumb() diretamente — isso quebra o site ao trocar de plugin de SEO"
            );
        }
    }
}
