<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Card com thumb em destaque (usado por modern-grid, grid e pela linha
 * principal do mix). Reconstrução limpa da estrutura pública observada em
 * `.bs-listing .listing-item` (post-{ID}, .item-inner, .featured, .img-cont,
 * .content-container .title, .post-meta) — sem reaproveitar código do
 * Publisher, apenas o "formato" da marcação renderizada.
 *
 * A thumb é exibida sempre que o post tiver uma (`has_post_thumbnail()`),
 * independente da opção `featured_image` do shortcode: verificado contra a
 * referência pública que o atributo `featured_image="0"` do
 * `bs-mix-listing-3-1`/`bs-grid-listing-1` da própria home NÃO some com a
 * imagem lá (ambos sempre mostram thumb quando existe) — então esse
 * atributo não governa a visibilidade da imagem no widget real.
 *
 * `show_meta`/`show_comments`/`show_badge` (via $args, não $options — são
 * específicos de QUEM chama este card, não do shortcode) permitem que cada
 * layout ligue/desligue partes: `grid` (Últimas notícias) não mostra meta
 * nem badge de categoria na referência; a linha principal do `mix` mostra
 * meta com contador de comentários (que o `grid` não usa).
 *
 * @var array<string, mixed> $args {
 *     @type bool                 $is_first      Se este é o 1º card da listagem (LCP).
 *     @type bool                 $show_meta     Mostra o bloco `.post-meta`. Padrão true.
 *     @type bool                 $show_comments Mostra o contador de comentários. Padrão false.
 *     @type bool                 $show_badge    Mostra o badge de categoria. Padrão true.
 *     @type array<string, mixed> $options       Opções vindas de Renderer::buildOptions().
 * }
 */

$postId = get_the_ID();
if (!$postId) {
    return;
}

$isFirst  = (bool) ($args['is_first'] ?? false);
$showMeta = (bool) ($args['show_meta'] ?? true);
$showComments = (bool) ($args['show_comments'] ?? false);
$showBadge = (bool) ($args['show_badge'] ?? true);
$showThumb = \Arena\Listing\Renderer::hasUsableThumbnail($postId);

$categories = get_the_category($postId);
$primaryCategory = $showBadge && $categories !== [] ? $categories[0] : null;

$authorId = (int) get_the_author_meta('ID');
$permalink = get_permalink($postId);
$commentCount = (int) get_comments_number($postId);
?>
<article <?php post_class('listing-item listing-item-featured'); ?>>
    <div class="item-inner">
        <div class="featured clearfix">
            <?php if ($primaryCategory): ?>
                <div class="term-badges floated">
                    <span class="term-badge" data-slug="<?php echo esc_attr($primaryCategory->slug); ?>">
                        <a href="<?php echo esc_url(get_category_link($primaryCategory)); ?>"><?php echo esc_html($primaryCategory->name); ?></a>
                    </span>
                </div>
            <?php endif; ?>
            <?php if ($showThumb): ?>
                <a class="img-cont" href="<?php echo esc_url($permalink); ?>">
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
            <?php else: ?>
                <div class="img-cont thumb-placeholder" aria-hidden="true"></div>
            <?php endif; ?>
        </div>
        <div class="content-container">
            <h2 class="title">
                <a href="<?php echo esc_url($permalink); ?>" class="post-url post-title"><?php echo esc_html(get_the_title($postId)); ?></a>
            </h2>
            <?php if ($showMeta): ?>
                <div class="post-meta">
                    <?php if ($authorId > 0): ?>
                        <a class="post-author-a" href="<?php echo esc_url(get_author_posts_url($authorId)); ?>">
                            <i class="post-author author"><?php echo esc_html(get_the_author()); ?></i>
                        </a>
                    <?php endif; ?>
                    <span class="time">
                        <time class="post-published updated" datetime="<?php echo esc_attr(get_the_date('c', $postId)); ?>"><?php echo esc_html(\Arena\Listing\Renderer::articleDate($postId)); ?></time>
                    </span>
                    <?php if ($showComments): ?>
                        <a class="comments" href="<?php echo esc_url(get_comments_link($postId)); ?>">
                            <?php echo \Arena\Listing\Renderer::commentIcon(); ?> <?php echo esc_html((string) $commentCount); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</article>
