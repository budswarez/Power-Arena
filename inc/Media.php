<?php
declare(strict_types=1);

namespace Arena;

/**
 * Shared `alt` text resolution for every place in the theme that renders an
 * attachment image (the 5 card partials in `template-parts/card/`,
 * `template-parts/single/featured.php` and `attachment.php`).
 *
 * All 7 call sites used to pass `'alt' => get_the_title($postId)` straight
 * into `get_the_post_thumbnail()`/`the_post_thumbnail()`/
 * `wp_get_attachment_image()`. Explicitly passing `alt` in the `$attr`
 * array REPLACES whatever the editor wrote in the attachment's own
 * `_wp_attachment_image_alt` meta — WordPress only falls back to that meta
 * when `alt` is absent from `$attr` entirely. On a newsroom where editors
 * write real alt text, the theme was silently discarding it and
 * substituting the headline, which is also the adjacent link text — so
 * screen readers announced the same string twice per card.
 *
 * `imageAlt()` reproduces the fallback WordPress would have used on its
 * own (the attachment's alt meta), but with the post title as the LAST
 * resort when the meta is empty/missing/whitespace-only — matching this
 * theme's previous (buggy) behaviour for attachments that never had alt
 * text set, while finally honouring real editor-authored alt text when
 * it exists.
 */
final class Media {
    /**
     * @param int    $attachmentId The attachment (thumbnail) post ID. 0/negative is treated as "no attachment".
     * @param string $fallback     Used when the attachment has no non-blank alt meta (typically the post title).
     */
    public static function imageAlt(int $attachmentId, string $fallback): string {
        if ($attachmentId > 0) {
            $alt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
            if (is_string($alt) && trim($alt) !== '') {
                return $alt;
            }
        }

        return $fallback;
    }

    /**
     * Marca um `<img>` como ACIMA DA DOBRA: prioridade alta, sem lazy-load, e
     * blindado contra lazy-loaders de plugin.
     *
     * POR QUE `skip-lazy` E NÃO SÓ `loading="eager"`: declarar `eager` não
     * basta. Medido na home de produção (mobile, throttling 4G lento + CPU 4×):
     * o lazy-loader do **EWWW Image Optimizer** (lazysizes) reescreve o `src`
     * para um placeholder base64, move a URL real para `data-src` e **ignora o
     * atributo `loading`**. Resultado: a imagem que definia o LCP só começava a
     * baixar depois de o JavaScript rodar — LCP de **6,7 s**, com a imagem
     * terminando em 6.648 ms.
     *
     * `skip-lazy` é o marcador que o EWWW respeita (está na lista de exclusões
     * em `classes/class-lazy-load.php`, testada como substring da tag `<img>`).
     * Não amarra o tema ao EWWW: é a convenção que WP Rocket, Perfmatters,
     * Autoptimize e o próprio lazysizes também reconhecem. Se um dia não houver
     * lazy-loader nenhum, a classe é inerte — só um nome de classe sem CSS.
     *
     * REGRA DE USO: só para imagens que o tema SABE estarem acima da dobra (a
     * primeira linha do mosaico, o destaque da matéria, o primeiro card de uma
     * listagem). Marcar imagens abaixo da dobra é pior que não marcar nenhuma —
     * cada download eager disputa banda com o próprio LCP.
     *
     * POR QUE `$prioridade` É SEPARADO de "acima da dobra": no mosaico da home a
     * primeira linha tem DOIS tiles do mesmo tamanho. Medido: proteger só o
     * primeiro não resolveu nada — as três imagens visíveis têm área idêntica
     * (58.282 px²), então o LCP simplesmente passou para o tile seguinte, que
     * continuava lazy e só baixava em 5.4 s. Todas precisam sair do lazy; mas
     * `fetchpriority="high"` em três imagens ao mesmo tempo anula o próprio
     * sentido de "prioridade" e faz as três disputarem a banda. Então: `eager` +
     * `skip-lazy` para todas as de cima, `fetchpriority` só para uma.
     *
     * @param array<string, string> $attr       Atributos que iriam para
     *                                          `get_the_post_thumbnail()`/`wp_get_attachment_image()`.
     * @param bool                  $prioridade Emitir `fetchpriority="high"`. Use `true`
     *                                          em NO MÁXIMO uma imagem por página — a
     *                                          candidata a LCP.
     * @return array<string, string>
     */
    public static function markAboveTheFold(array $attr, bool $prioridade = true): array {
        $attr['loading'] = 'eager';
        $attr['class']   = trim(($attr['class'] ?? '') . ' skip-lazy');

        if ($prioridade) {
            $attr['fetchpriority'] = 'high';
        }

        return $attr;
    }

    /**
     * URL of the theme's own DEFAULT placeholder image (BUG 4, task-uifix):
     * a post with no usable thumbnail (see
     * Arena\Listing\Renderer::hasUsableThumbnail()) used to leave an empty,
     * non-clickable box where the thumb would be — every card partial now
     * renders this image instead, wrapped in the SAME anchor a real
     * thumbnail gets, so the area stays clickable and never collapses.
     */
    public static function placeholderUrl(): string {
        /*
         * O dono do site pode escolher a própria imagem padrão no painel
         * (Arena → Blocos e listagens → "Imagem padrão dos cards"). Sem opção
         * salva, ou se o anexo escolhido não existir mais, cai no SVG que vem
         * com o tema — nunca retorna vazio, porque um card sem imagem volta a
         * ser uma área não clicável (era exatamente o bug que este
         * placeholder resolveu).
         */
        $escolhida = Settings::defaultThumbnailId();
        if ($escolhida !== null) {
            $url = wp_get_attachment_image_url($escolhida, 'arena-card');
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return get_template_directory_uri() . '/assets/img/placeholder.svg';
    }

    /**
     * Builds the `<img>` markup for the placeholder above. Explicit
     * `width`/`height` match the real `arena-card` registered image size
     * (760x428, see Arena\Setup::boot()) so a thumbnail-less card reserves
     * exactly the same box a real thumbnail would — no layout shift if a
     * thumbnail is added to the post later.
     *
     * Callers are responsible for wrapping the returned markup in the same
     * anchor (`<a href="<?php echo esc_url(get_permalink($postId)); ?>">`)
     * they'd use for a real thumbnail — this method only ever returns the
     * `<img>` itself, never a link, so it stays reusable regardless of
     * which wrapper element (`.img-cont`, `.img-holder`, `.hero-tile__link`)
     * the calling card partial uses.
     *
     * @param string $alt      Accessible name for the image — pass the post
     *                         title, exactly like a real thumbnail's own
     *                         `imageAlt()` fallback above, so the wrapping
     *                         anchor always has a discernible name.
     * @param string $imgClass Extra class(es) matching the exact classes the
     *                         calling card partial would give a REAL
     *                         thumbnail's `<img>` (e.g.
     *                         `'attachment-arena-card hero-tile__img--compact'`)
     *                         so size/context-specific CSS keeps applying
     *                         identically to the placeholder.
     */
    public static function placeholderImg(string $alt, string $imgClass = ''): string {
        $classes = trim('thumb-placeholder-img ' . $imgClass);

        return sprintf(
            '<img src="%s" width="760" height="428" alt="%s" loading="lazy" decoding="async" class="%s" />',
            esc_url(self::placeholderUrl()),
            esc_attr($alt),
            esc_attr($classes)
        );
    }
}
