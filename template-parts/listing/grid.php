<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Layout `grid`: grade de N colunas de cards com thumb (esquema claro/escuro
 * conforme `bs-text-color-scheme`).
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
$columns = (int) ($options['columns'] ?? 4);
if ($columns < 1) {
    $columns = 4;
}
?>
<div class="bs-listing bs-listing-grid <?php echo esc_attr(trim($scheme . ' ' . $visibilityClass)); ?>">
    <?php echo $options['heading_html'] ?? ''; ?>
    <div class="listing listing-grid clearfix columns-<?php echo esc_attr((string) $columns); ?>">
        <?php if ($query->have_posts()): ?>
            <?php
            $index = 0;
            while ($query->have_posts()):
                $query->the_post();
                $index++;
                get_template_part('template-parts/card/featured', null, [
                    'is_first'   => $index === 1,
                    'above_fold' => $blocoAcimaDaDobra && $index === 1,
                    'show_meta'  => false,
                    'show_badge' => false,
                    'options'    => $options,
                ]);
            endwhile;
            ?>
        <?php else: ?>
            <p class="listing-empty"><?php esc_html_e('Nenhum post encontrado.', 'arena'); ?></p>
        <?php endif; ?>
    </div>
</div>
