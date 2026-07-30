<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Layout `blog`: coluna única de cards com resumo (`.post-summary`).
 *
 * @var array<string, mixed> $args {
 *     @type WP_Query             $query   Query já executada (Renderer).
 *     @type array<string, mixed> $options Opções vindas de Renderer::buildOptions().
 * }
 */

$query = $args['query'] instanceof WP_Query ? $args['query'] : new WP_Query(['post__in' => [0]]);
$options = is_array($args['options'] ?? null) ? $args['options'] : [];
// Só o PRIMEIRO bloco de listagem da requisição pode conter o elemento LCP.
// Ver Arena\Media::claimAboveTheFoldBlock(): sem esse trinco, cada bloco marcava
// o próprio primeiro card e a home ficava com 6 imagens em fetchpriority=high.
$blocoAcimaDaDobra = \Arena\Media::claimAboveTheFoldBlock();


$scheme = ($options['dark_scheme'] ?? false) ? 'bs-dark-scheme' : 'bs-light-scheme';
$visibilityClass = (string) ($options['visibility_class'] ?? '');
?>
<div class="bs-listing bs-listing-blog <?php echo esc_attr(trim($scheme . ' ' . $visibilityClass)); ?>">
    <?php echo $options['heading_html'] ?? ''; ?>
    <div class="listing listing-blog clearfix columns-1">
        <?php if ($query->have_posts()): ?>
            <?php
            $index = 0;
            while ($query->have_posts()):
                $query->the_post();
                $index++;
                get_template_part('template-parts/card/list', null, [
                    'is_first' => $index === 1,
                    'above_fold' => $blocoAcimaDaDobra && $index === 1,
                    'options'  => $options,
                    'variant'  => 'blog',
                ]);
            endwhile;
            ?>
        <?php else: ?>
            <p class="listing-empty"><?php esc_html_e('Nenhum post encontrado.', 'arena'); ?></p>
        <?php endif; ?>
    </div>
</div>
