<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Layout `modern-grid`: hero + itens com thumb em destaque (~5 posts).
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
<div class="bs-listing bs-listing-modern-grid <?php echo esc_attr($scheme); ?>">
    <?php echo $options['heading_html'] ?? ''; ?>
    <div class="listing listing-modern-grid clearfix">
        <?php if ($query->have_posts()): ?>
            <div class="mg-row">
                <?php
                $index = 0;
                while ($query->have_posts()):
                    $query->the_post();
                    $index++;
                    ?>
                    <div class="mg-col mg-col-<?php echo esc_attr((string) $index); ?>">
                        <?php
                        get_template_part('template-parts/card/featured', null, [
                            'is_first' => $index === 1,
                            'options'  => $options,
                        ]);
                        ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="listing-empty"><?php esc_html_e('Nenhum post encontrado.', 'arena'); ?></p>
        <?php endif; ?>
    </div>
</div>
