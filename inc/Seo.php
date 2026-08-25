<?php
declare(strict_types=1);

namespace Arena;

/**
 * Ponte entre o tema e o plugin de SEO instalado, para o breadcrumb.
 *
 * Por que existe: sete templates (single, archive, page, search, index, 404,
 * attachment) chamavam `yoast_breadcrumb()` direto. Quando o dono do site
 * trocou o Yoast pelo Rank Math, o breadcrumb simplesmente desapareceu do site
 * — nenhum erro, nenhuma pista, só sumiu, porque a função deixou de existir e
 * cada template silenciosamente pulava a chamada. E o próprio Rank Math não
 * insere breadcrumb sozinho: ele avisa no painel que o tema precisa chamar
 * `rank_math_the_breadcrumbs()`.
 *
 * Agora existe um único ponto que descobre o que está disponível. Trocar de
 * plugin de SEO volta a ser uma troca de plugin, não uma edição de sete
 * arquivos.
 *
 * Ordem de preferência: Rank Math → Yoast → SEOPress → nada. Não há
 * "breadcrumb próprio do tema" de propósito: o plugin de SEO também emite o
 * JSON-LD `BreadcrumbList` correspondente, e uma trilha visual que não bate
 * com a estruturada é pior do que trilha nenhuma.
 */
final class Seo {
    /**
     * Descrição curta para os cartões de listagem.
     *
     * A meta description é um campo editorial independente do excerpt e,
     * quando existe, costuma ser o resumo mais adequado para apresentar o
     * post em categorias, buscas e demais listagens. O excerpt continua como
     * fallback para posts antigos ou sem descrição SEO preenchida.
     */
    public static function postDescription(int $postId): string {
        if ($postId <= 0) {
            return '';
        }

        // Rank Math: usa a API pública para não depender de como a versão
        // instalada armazena o campo no post meta.
        if (class_exists('\\RankMath\\Post') && is_callable(['\\RankMath\\Post', 'get_meta'])) {
            $description = \RankMath\Post::get_meta('description', $postId);
            if (is_string($description) && trim($description) !== '') {
                return trim($description);
            }
        }

        // Compatibilidade com Rank Math e outros plugins que persistem a
        // descrição diretamente como post meta.
        foreach (['rank_math_description', '_yoast_wpseo_metadesc', '_seopress_titles_desc', '_aioseo_description'] as $metaKey) {
            $description = get_post_meta($postId, $metaKey, true);
            if (is_string($description) && trim($description) !== '') {
                return trim($description);
            }
        }

        return trim((string) get_the_excerpt($postId));
    }

    /**
     * Renderiza o breadcrumb do plugin ativo, envolvido em `$before`/`$after`.
     *
     * @return bool true se algo foi renderizado — o chamador não precisa saber
     *              qual plugin respondeu, só se deve ou não reservar o espaço.
     */
    public static function breadcrumb(
        string $before = '<nav class="arena-breadcrumb">',
        string $after = '</nav>'
    ): bool {
        /**
         * Filtro `arena_breadcrumb_html`: permite ao tema filho substituir ou
         * suprimir a trilha sem tocar em template nenhum (devolver '' suprime).
         * Também é o que torna o caminho "sem provedor" testável.
         *
         * @param string $html HTML da trilha vindo do plugin de SEO ativo.
         */
        $html = (string) apply_filters('arena_breadcrumb_html', self::breadcrumbHtml());

        if (trim($html) === '') {
            return false; // nem o wrapper: um <nav> vazio é um marco de navegação sem conteúdo
        }

        echo $before . $html . $after; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML do plugin de SEO, já escapado por ele
        return true;
    }

    /**
     * HTML da trilha, sem wrapper. Separado de breadcrumb() para ser testável
     * sem capturar saída e para permitir que um template decida não renderizar
     * o wrapper quando não há trilha.
     */
    public static function breadcrumbHtml(): string {
        // Rank Math: `rank_math_get_breadcrumbs()` devolve string (preferida);
        // versões antigas só têm a variante que imprime.
        if (function_exists('rank_math_get_breadcrumbs')) {
            $html = rank_math_get_breadcrumbs(['wrap_before' => '', 'wrap_after' => '']);
            if (is_string($html) && trim($html) !== '') {
                return $html;
            }
        }

        if (function_exists('rank_math_the_breadcrumbs')) {
            ob_start();
            rank_math_the_breadcrumbs();
            $html = (string) ob_get_clean();
            if (trim($html) !== '') {
                return $html;
            }
        }

        /*
         * Yoast por captura de saída em vez de `yoast_breadcrumb('', '', false)`:
         * o 3º parâmetro ($display) existe no plugin real, mas nem toda versão
         * (nem os stubs de teste) o implementa — e uma chamada que devolve null
         * viraria trilha vazia sem aviso. Capturar a impressão funciona nas duas
         * formas.
         */
        if (function_exists('yoast_breadcrumb')) {
            ob_start();
            yoast_breadcrumb('', '');
            $html = (string) ob_get_clean();
            if (trim($html) !== '') {
                return $html;
            }
        }

        if (function_exists('seopress_display_breadcrumbs')) {
            ob_start();
            seopress_display_breadcrumbs();
            $html = (string) ob_get_clean();
            if (trim($html) !== '') {
                return $html;
            }
        }

        return '';
    }

    /** Qual plugin está fornecendo a trilha (para diagnóstico e testes). */
    public static function breadcrumbProvider(): string {
        if (function_exists('rank_math_get_breadcrumbs') || function_exists('rank_math_the_breadcrumbs')) {
            return 'rank-math';
        }
        if (function_exists('yoast_breadcrumb')) {
            return 'yoast';
        }
        if (function_exists('seopress_display_breadcrumbs')) {
            return 'seopress';
        }

        return 'nenhum';
    }
}
