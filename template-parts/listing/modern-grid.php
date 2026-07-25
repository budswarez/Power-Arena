<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Layout `modern-grid`: hero mosaico ("posts em destaque") — medido contra
 * a referência pública `bs-modern-grid-listing-7` (5 posts): 1ª linha com
 * 2 tiles grandes (badge + meta), linhas seguintes com 3 tiles compactos
 * (só título) — ver `template-parts/card/hero.php` e
 * `assets/src/css/main.css` para os valores medidos (aspect-ratio por
 * linha, overlay, hover).
 *
 * Generalizado para qualquer `count`: a 1ª linha leva até 2 posts, cada
 * linha seguinte leva até 3 — reproduz o padrão 2+3 observado sem travar
 * em exatamente 5 posts.
 *
 * @var array<string, mixed> $args {
 *     @type WP_Query             $query   Query já executada (Renderer).
 *     @type array<string, mixed> $options Opções vindas de Renderer::buildOptions().
 * }
 */

$query = $args['query'] instanceof WP_Query ? $args['query'] : new WP_Query(['post__in' => [0]]);
$options = is_array($args['options'] ?? null) ? $args['options'] : [];

$scheme = ($options['dark_scheme'] ?? false) ? 'bs-dark-scheme' : 'bs-light-scheme';

$firstRowSize = 2;
$otherRowSize = 3;

$rows = [];
$buffer = [];
$index = 0;

if ($query->have_posts()) {
    while ($query->have_posts()) {
        $query->the_post();
        $index++;

        ob_start();
        get_template_part('template-parts/card/hero', null, [
            'is_first' => $index === 1,
            'compact'  => $index > $firstRowSize,
            'options'  => $options,
        ]);
        $buffer[] = (string) ob_get_clean();

        $rowLimit = $rows === [] ? $firstRowSize : $otherRowSize;
        if (count($buffer) === $rowLimit) {
            $rows[] = $buffer;
            $buffer = [];
        }
    }
    if ($buffer !== []) {
        $rows[] = $buffer;
    }
}
?>
<div class="bs-listing bs-listing-modern-grid <?php echo esc_attr($scheme); ?>">
    <?php echo $options['heading_html'] ?? ''; ?>
    <div class="listing listing-modern-grid clearfix">
        <?php if ($rows !== []): ?>
            <?php foreach ($rows as $rowIndex => $cols): ?>
                <div class="mg-row mg-row-<?php echo esc_attr((string) ($rowIndex + 1)); ?> clearfix">
                    <?php foreach ($cols as $colIndex => $cardHtml): ?>
                        <div class="mg-col mg-col-<?php echo esc_attr((string) ($colIndex + 1)); ?>">
                            <?php echo $cardHtml; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="listing-empty"><?php esc_html_e('Nenhum post encontrado.', 'arena'); ?></p>
        <?php endif; ?>
    </div>
</div>
