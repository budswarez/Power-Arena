<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Layout `archive`: the "blog-5" card (300px thumb + title/meta/excerpt),
 * reused by category/tag/author archives and by search results (Task 3
 * brief) — measured reference wrapper is
 * `<div class="listing listing-blog listing-blog-5 clearfix">` with items
 * `listing-item-blog-5`.
 *
 * @var array<string, mixed> $args {
 *     @type WP_Query             $query   Query já executada (Renderer).
 *     @type array<string, mixed> $options Opções vindas de Renderer::buildOptions().
 * }
 */

$query = $args['query'] instanceof WP_Query ? $args['query'] : new WP_Query(['post__in' => [0]]);
$options = is_array($args['options'] ?? null) ? $args['options'] : [];

$scheme = ($options['dark_scheme'] ?? false) ? 'bs-dark-scheme' : 'bs-light-scheme';
?>
<div class="bs-listing bs-listing-archive <?php echo esc_attr($scheme); ?>">
    <?php echo $options['heading_html'] ?? ''; ?>
    <div class="listing listing-blog listing-blog-5 clearfix">
        <?php if ($query->have_posts()): ?>
            <?php
            $index = 0;
            while ($query->have_posts()):
                $query->the_post();
                $index++;
                get_template_part('template-parts/card/blog5', null, [
                    'is_first' => $index === 1,
                    'options'  => $options,
                ]);
            endwhile;
            ?>
        <?php else: ?>
            <p class="listing-empty"><?php esc_html_e('Nenhum post encontrado.', 'arena'); ?></p>
        <?php endif; ?>
    </div>
</div>
