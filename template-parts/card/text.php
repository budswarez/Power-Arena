<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Card compacto (usado por `mix` para os itens além do primeiro): thumb
 * pequena + título numa linha. Mostra a thumb sempre que o post tiver uma —
 * verificado contra a referência pública que as linhas compactas do
 * `bs-mix-listing-3-1` (com `featured_image="0"` no shortcode) SEMPRE
 * carregam uma thumb pequena (~86x64px); esse atributo não governa a
 * visibilidade da imagem aqui (mesmo raciocínio de `card/featured.php`).
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

$isFirst = (bool) ($args['is_first'] ?? false);
$showThumb = \Arena\Listing\Renderer::hasUsableThumbnail($postId);

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
                        'alt'      => \Arena\Media::imageAlt((int) get_post_thumbnail_id($postId), get_the_title($postId)),
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
        <?php get_template_part('template-parts/card/meta', null, ['post_id' => $postId, 'show_author' => false, 'show_comments' => false]); ?>
    </div>
</article>
