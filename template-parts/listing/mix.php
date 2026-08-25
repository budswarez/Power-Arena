<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Layout `mix`: pensado para contexto de 3 colunas (`listing-mix-3-1`) — 1º
 * post em card com thumb (`card/featured`), demais em lista compacta de
 * texto (`card/text`, sem thumb por padrão).
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

$firstHtml = '';
$restHtml = '';
$index = 0;

if ($query->have_posts()) {
    while ($query->have_posts()) {
        $query->the_post();
        $index++;
        ob_start();
        if ($index === 1) {
            get_template_part('template-parts/card/featured', null, [
                'is_first'      => true,
                'above_fold'    => $blocoAcimaDaDobra,
                'show_comments' => true,
                'options'       => $options,
            ]);
            $firstHtml = (string) ob_get_clean();
        } else {
            get_template_part('template-parts/card/text', null, [
                'is_first' => false,
                'above_fold' => false,
                'options'  => $options,
            ]);
            $restHtml .= (string) ob_get_clean();
        }
    }
}
?>
<div class="bs-listing bs-listing-mix <?php echo esc_attr(trim($scheme . ' ' . $visibilityClass)); ?>">
    <?php echo $options['heading_html'] ?? ''; ?>
    <div class="listing listing-mix-3-1 clearfix">
        <?php if ($index > 0): ?>
            <div class="row-1"><?php echo $firstHtml; ?></div>
            <?php if ($restHtml !== ''): ?>
                <div class="row-2"><?php echo $restHtml; ?></div>
            <?php endif; ?>
        <?php else: ?>
            <p class="listing-empty"><?php esc_html_e('Nenhum post encontrado.', 'arena'); ?></p>
        <?php endif; ?>
    </div>
</div>
