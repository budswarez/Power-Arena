<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Card compacto, apenas texto (usado por `mix` para os itens além do
 * primeiro). Sem thumb por padrão — só exibe quando `featured_image` é
 * explicitamente truthy nas opções, pois este card serve o contexto de
 * coluna estreita (3 colunas) onde o Publisher usa uma lista enxuta.
 *
 * @var array<string, mixed> $args {
 *     @type bool                 $is_first Se este é o 1º card da listagem (LCP).
 *     @type array<string, mixed> $options  Opções vindas de Renderer::buildOptions().
 * }
 */

$postId = get_the_ID();
if (!$postId) {
    return;
}

$options = is_array($args['options'] ?? null) ? $args['options'] : [];
$isFirst = (bool) ($args['is_first'] ?? false);
$showThumb = (($options['featured_image'] ?? false) === true) && has_post_thumbnail($postId);

$permalink = get_permalink($postId);
?>
<article <?php post_class('listing-item listing-item-text'); ?>>
    <div class="item-inner clearfix">
        <?php if ($showThumb): ?>
            <div class="featured featured-type-featured-image">
                <a class="img-holder" href="<?php echo esc_url($permalink); ?>">
                    <?php
                    $imgAttr = [
                        'class'    => 'attachment-arena-card',
                        'decoding' => 'async',
                        'alt'      => get_the_title($postId),
                    ];
                    if ($isFirst) {
                        $imgAttr['fetchpriority'] = 'high';
                        $imgAttr['loading'] = 'eager';
                    }
                    echo get_the_post_thumbnail($postId, 'arena-card', $imgAttr);
                    ?>
                </a>
            </div>
        <?php endif; ?>
        <div class="title">
            <a href="<?php echo esc_url($permalink); ?>" class="post-url post-title"><?php echo esc_html(get_the_title($postId)); ?></a>
        </div>
        <div class="post-meta">
            <span class="time">
                <time class="post-published updated" datetime="<?php echo esc_attr(get_the_date('c', $postId)); ?>"><?php echo esc_html(get_the_date('j M, Y', $postId)); ?></time>
            </span>
        </div>
    </div>
</article>
