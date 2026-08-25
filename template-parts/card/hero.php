<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Card do hero (`modern-grid`, mosaico "posts em destaque"): a imagem cobre
 * o tile inteiro, com um scrim escuro semi-transparente ancorado no rodapé
 * (`.content-container`) carregando o badge de categoria e o título por
 * cima da imagem — reconstrução limpa do padrão medido em
 * `.listing-mg-type-1` (ver assets/src/css/main.css para os valores
 * medidos: aspect-ratio por linha, overlay `rgba(0,0,0,.42)`, hover zoom).
 *
 * Os 2 primeiros posts (`mg-row-1`) saem "grandes" com badge + meta; os
 * demais (`mg-row-2+`) saem "compactos" (`$compact`): sem badge, sem meta,
 * só título — bate com o markup público observado (posts 3-5 do
 * modern-grid-7 de referência não têm `.term-badges` nem `.post-meta`).
 *
 * @var array<string, mixed> $args {
 *     @type bool                 $is_first Se este é o 1º card da listagem (LCP).
 *     @type bool                 $high_priority Se é um dos candidatos reais a LCP.
 *     @type bool                 $compact  Se este é um tile pequeno (sem badge/meta).
 *     @type array<string, mixed> $options  Opções vindas de Renderer::buildOptions().
 * }
 */

$postId = get_the_ID();
if (!$postId) {
    return;
}

$isFirst = (bool) ($args['is_first'] ?? false);
$highPriority = (bool) ($args['high_priority'] ?? $isFirst);
// Tiles acima da dobra saem do lazy-load; o layout decide quais candidatos
// reais a LCP recebem `fetchpriority` (os dois grandes na home mobile).
// Sem `above_fold`, cai em `is_first` — mantém o comportamento de quem chama
// este partial sem passar a flag (mix.php, por exemplo).
$aboveFold = (bool) ($args['above_fold'] ?? $isFirst);
$compact = (bool) ($args['compact'] ?? false);
$showThumb = \Arena\Listing\Renderer::hasUsableThumbnail($postId);

$categories = get_the_category($postId);
$primaryCategory = $categories !== [] ? $categories[0] : null;

$permalink = get_permalink($postId);
?>
<article <?php post_class('listing-item listing-item-hero hero-tile' . ($compact ? ' hero-tile-compact' : '')); ?>>
    <?php if ($showThumb): ?>
        <a class="img-cont hero-tile__link" href="<?php echo esc_url($permalink); ?>">
            <?php
            $imgAttr = [
                'class'    => 'attachment-arena-card hero-tile__img' . ($compact ? ' hero-tile__img--compact' : ''),
                'decoding' => 'async',
                'alt'      => \Arena\Media::imageAlt((int) get_post_thumbnail_id($postId), get_the_title($postId)),
            ];
            if ($aboveFold) {
                // Todos os tiles de cima saem do lazy-load (`eager` + `skip-lazy`).
                // O layout promove só os candidatos reais a LCP; tiles compactos
                // continuam sem prioridade alta. Ver
                // Arena\Media::markAboveTheFold().
                $imgAttr = \Arena\Media::markAboveTheFold($imgAttr, $highPriority);
            }
            echo get_the_post_thumbnail($postId, 'arena-card', $imgAttr);
            ?>
        </a>
    <?php else: ?>
        <a class="img-cont hero-tile__link thumb-placeholder" href="<?php echo esc_url($permalink); ?>">
            <?php
            $placeholderClass = 'attachment-arena-card hero-tile__img' . ($compact ? ' hero-tile__img--compact' : '');
            echo \Arena\Media::placeholderImg(get_the_title($postId), $placeholderClass);
            ?>
        </a>
    <?php endif; ?>
    <?php if (!$compact && $primaryCategory): ?>
        <div class="term-badges floated">
            <span class="term-badge" data-slug="<?php echo esc_attr($primaryCategory->slug); ?>">
                <a href="<?php echo esc_url(get_category_link($primaryCategory)); ?>"><?php echo esc_html($primaryCategory->name); ?></a>
            </span>
        </div>
    <?php endif; ?>
    <div class="content-container">
        <h2 class="title">
            <a href="<?php echo esc_url($permalink); ?>" class="post-url post-title"><?php echo esc_html(get_the_title($postId)); ?></a>
        </h2>
        <?php if (!$compact): ?>
            <?php get_template_part('template-parts/card/meta', null, ['post_id' => $postId, 'show_comments' => false]); ?>
        <?php endif; ?>
    </div>
</article>
